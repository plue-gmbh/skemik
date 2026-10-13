<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Relation;
use Domain\Status;
use Feed\PublicFilter;
use Http\CacheHeaders;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Http\TooManyRequests;
use Http\Upload;
use Profile\Profile;
use Profile\Seed\Media;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RateLimit;
use Storage\RelationRepository;
use Support\Clock;
use Support\Json;
use Support\Thumbnail;
use Support\Uuid;
use Support\Words;

/* The library's writes and its two addresses; what one upload becomes is
   `Intake`'s. An upload lands as a draft, like every other record, and its file
   is served under its record's reading, as the feed reads the record: a
   session's read scope, else a key's `read` grants, else the stranger's. The
   same file twice is two assets: a second upload is somebody's second use, and
   merging the two would repoint a page that never asked. Several files are
   several requests, so each is taken or refused alone and leaves its own
   sentence. */
final readonly class MediaController
{
  private const SHOWN = ['image/', 'audio/', 'video/'];
  private const DRAWING_POLICY = "default-src 'none'; style-src 'unsafe-inline'";

  public function __construct(
    private CollectionStore $collections,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private Showing $showing,
    private Rows $rows,
    private Intake $intake,
    private Authorizer $authorizer,
    private Clock $clock,
    private Session $session,
    private Thumbnail $thumbnail,
    private RateLimit $cold,
    private Profile $profile,
    private Csrf $csrf,
    private Words $words,
    private string $directory,
    private CacheHeaders $cache = new CacheHeaders(),
    private Json $json = new Json(),
  ) {}

  /* The island asks for JSON, since a redirect it followed would spend the kept
     sentence on a page nobody sees. */
  public function upload(Request $request): Response
  {
    $this->csrf->require($request);
    $library = $this->holder();
    $this->authorizer->requireHuman(Capability::WriteContent, $library);
    try {
      $this->note('uploaded', $this->intake->taken($library, $request->upload, Status::Draft)->id->value);
    } catch (UploadRefused $refused) {
      $this->note('refused', $refused->getMessage());
    }
    $address = '/c/' . $library->slug->value;

    return $request->wantsJson() ? Response::json($this->json->encode(['address' => $address]))
      : Response::redirect($address);
  }

  /* A record's file exchanged on its own screen: a translation's own bytes, or
     a better scan of the same picture. Answered to the island alone, which
     shows a refusal where the library's page would. */
  public function replace(Request $request, string $slug, string $id): Response
  {
    $this->csrf->require($request);
    $library = $this->holder();
    $this->authorizer->requireHuman(Capability::WriteContent, $library);
    $asset = $this->asset($library, $slug, $id);
    try {
      $replaced = $this->intake->replaced($asset, $request->upload, $this->relations->outbound($asset->id));
    } catch (UploadRefused $refused) {
      return Response::json($this->json->encode(['error' => $refused->getMessage()]), 422);
    }
    $this->intake->release($asset);

    return Response::json($this->json->encode(['address' => Rows::address($library, $replaced)]));
  }

  public function file(?Account $account, Request $request, string $name): Response
  {
    [$asset, $mime] = $this->readable($account, $name);
    $shown = array_any(self::SHOWN, fn (string $kind): bool => str_starts_with($mime, $kind));
    $named = (string) $asset->props->string('name');
    $file = rawurlencode(Intake::fileName($named, $mime) ? $named : $named . '.' . (Media::FORMATS[$mime] ?? ''));
    $headers = ['Content-Type' => $mime, ...$this->cache->asset($this->shared($account, $request)),
      'Content-Disposition' => ($shown ? 'inline' : 'attachment') . "; filename*=UTF-8''" . $file];
    $drawing = $mime === 'image/svg+xml' ? ['Content-Security-Policy' => self::DRAWING_POLICY] : [];

    return Response::ranged($this->path($name, $mime), $request->header('Range'), [...$headers, ...$drawing]);
  }

  /* Cold previews are rate-limited per address and warm ones are not: the work
     a stranger can provoke is the library, once. */
  public function thumb(?Account $account, Request $request, string $name): Response
  {
    [, $mime] = $this->readable($account, $name);
    $preview = $this->thumbnail->cached($name);
    if ($preview === null && $this->thumbnail->handles($mime)) {
      $this->cold->hit('thumb ' . $request->client) || throw new TooManyRequests('cold previews');
      $preview = $this->thumbnail->make($name, $mime, $this->path($name, $mime));
    }
    $headers = ['Content-Type' => 'image/webp', ...$this->cache->asset($this->shared($account, $request))];

    return $preview === null ? throw EntityNotFound::withId($name)
      : Response::ranged($preview, $request->header('Range'), $headers);
  }

  /* What the grid draws beside its tiles: how often each is used, under this
     reader's scope as the record screen counts it, the largest file this server
     takes, stated before the picker opens, and what the last upload left.
     @param list<SheetRow> $rows
     @return array<string, array<string, int>|string|bool> */
  public function library(Account $account, Collection $collection, array $rows): array
  {
    $uses = [];
    foreach ($rows as $row) {
      $uses[$row->entity->id->value] = count(array_filter($this->showing->edges($row->entity),
        fn (Relation $edge): bool => $this->pointing($account, $edge->subject)));
    }

    return ['uses' => $uses, 'limit' => Intake::size(Upload::limit()),
      'uploads' => $this->authorizer->writes($account, $collection),
      'uploaded' => array_map($this->uploaded(...), $this->noted('uploaded')), 'refused' => $this->noted('refused')];
  }

  private function note(string $name, string $line): void
  {
    $this->session->keep($name, implode("\n", [...$this->noted($name), $line]));
  }

  /** @return list<string> */
  private function noted(string $name): array
  {
    return array_values(array_filter(explode("\n", (string) $this->session->take($name))));
  }

  private function pointing(Account $account, EntityId $subject): bool
  {
    try {
      $record = $this->entities->find($subject);
    } catch (EntityNotFound) {
      return false;
    }

    return $record->deletedAt === null && $this->rows->readable($account, $record);
  }

  /* An image asks for its alt text straight away, since that is the field it is useless without. */
  private function uploaded(string $id): string
  {
    try {
      $asset = $this->entities->find(new EntityId($id));
    } catch (EntityNotFound) {
      $asset = null;
    }
    $image = str_starts_with((string) $asset?->props->string('encoding_format'), 'image/');

    return $asset === null ? '' : $this->words->get($image ? 'media.uploaded_image' : 'media.uploaded',
      ['name' => (string) $asset->props->string('name')]);
  }

  /* The first collection of the type, as the feed answers a record under it. */
  private function holder(): Collection
  {
    $type = $this->profile->byType('MediaObject')->url();

    return array_find($this->collections->all(), fn (Collection $held): bool => $held->type === $type)
      ?? throw EntityNotFound::withId('MediaObject');
  }

  /* Another collection's record, a trashed one and a malformed id all read as none. */
  private function asset(Collection $library, string $slug, string $id): Entity
  {
    try {
      $asset = $slug === $library->slug->value ? $this->entities->find(new EntityId($id)) : null;
    } catch (MalformedValue) {
      $asset = null;
    }

    return $asset !== null && $asset->type === $library->type && $asset->deletedAt === null ? $asset
      : throw EntityNotFound::withId($id);
  }

  /* Any record holding the file opens it: a translation starts on the original's,
     and its draft must not close what the published original shows.
     @return array{Entity, string} the record and its stored type */
  private function readable(?Account $account, string $name): array
  {
    $library = $this->holder();
    $holding = preg_match(Uuid::SHAPE, $name) !== 1 ? [] : $this->entities->list(new Query($library->type,
      ['content_url' => [Intake::ADDRESS . $name]], perPage: Query::MAX_PER_PAGE));
    $reading = $this->reading($account);
    $asset = array_find($holding, fn (Entity $held): bool => $reading->passes($held, $library))
      ?? throw EntityNotFound::withId($name);

    return [$asset, (string) $asset->props->string('encoding_format')];
  }

  /* A key sent beside a session is not asked: the two paths never blur. */
  private function reading(?Account $account): PublicFilter
  {
    $rules = $account === null ? $this->authorizer->machineReads() : null;

    return new PublicFilter($this->profile, $this->clock, $rules, $account?->read);
  }

  /* A shared cache may hold the answer only where neither a session nor a key
     was in the request: with a cookie the answer carries one back, and a
     `Set-Cookie` in a cache is the one way a session could reach somebody else;
     a key's reading is its own. */
  private function shared(?Account $account, Request $request): bool
  {
    return ($account === null || $this->authorizer->readsAsPublic($account, 'MediaObject'))
      && $request->cookie('session') === null && $request->bearer() === null;
  }

  /* A row whose file is gone answers as the asset nobody has. */
  private function path(string $name, string $mime): string
  {
    $path = $this->directory . '/' . $name . '.' . (Media::FORMATS[$mime] ?? '');

    return is_file($path) ? $path : throw EntityNotFound::withId($name);
  }
}
