<?php

declare(strict_types=1);

namespace Feed;

use Auth\Account;
use Auth\Authorizer;
use Domain\Entity;
use Domain\Locale;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\Seed\Media;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RelationRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\Words;
use Support\Zip;

/* The dump a house takes with it, written into one box: a tree per format, a
   file per record and a listing per page, the files under `media/`, and
   `manifest.jsonld` at the root. It runs under the reader's read scope through
   `PublicFilter`, so every question of what comes out is that one object's
   answer and the box can never hold a record its reader could not open on
   screen, nor lack one they could; the role is not consulted, since a `viewer`
   reading everything dumps everything. The catalogue shows what the taker may
   arrange as a key that arranges it reads it, since which fields leave the
   house is the one thing a dump posted back could not restore otherwise. The
   manifest's record keys are this app's own: custody has no home in the
   vocabulary, which is why it travels beside the documents. */
final readonly class Export
{
  public const FORMATS = ['jsonld', 'html', 'csv', 'md'];
  public const FILES = 'media/';
  private const AUTHORITATIVE = 'jsonld';

  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private CollectionStore $collections,
    private SettingStore $settings,
    private PublicFilter $filter,
    private Authorizer $authorizer,
    private Account $taker,
    private string $origin,
    private string $media,
    private string $actor,
    private Zip $box,
    private Clock $clock,
    private Terms $terms,
    private Words $words,
    private Json $json = new Json(),
  ) {}

  /* Said before the run, since the box is as large as the files it holds. */
  public function measure(): DumpSize
  {
    $records = 0;
    foreach ($this->settings->locales() as $locale) {
      foreach ($this->readable() as $held) {
        $query = $this->filter->query(new Query($held->type, locale: $locale), $held);
        $records += $query === null ? 0 : $this->entities->count($query);
      }
    }
    $files = $this->files();

    return new DumpSize($records, count($files),
      array_sum(array_map(fn (string $file): int => (int) filesize($file), $files)));
  }

  /** @return int the records written, over every locale the house holds */
  public function write(): int
  {
    $entries = [];
    foreach ($this->settings->locales() as $locale) {
      $this->put(self::AUTHORITATIVE . '/' . $locale->tag . '/index.jsonld', $this->json->encode(
        $this->catalog($locale)->collections($this->collections->all(), $locale)));
      foreach ($this->readable() as $held) {
        $entries = [...$entries, ...$this->collection($held, $locale)];
      }
    }
    foreach ($this->files() as $path => $file) {
      $this->box->addFile($path, $file);
    }
    $this->put('manifest.jsonld', $this->json->encode($this->manifest($entries)));
    $this->box->close();

    return count($entries);
  }

  /** @return list<Collection> */
  private function readable(): array
  {
    return array_values(array_filter($this->collections->all(), $this->filter->reads(...)));
  }

  /* A page at a time, and the listing written per page as the address serves
     it: a collection of 27 000 articles is one walk and never one document, and
     the serialisers are the page's, so what they remember leaves with it.
     @return list<array<string, mixed>> the manifest entries of this collection */
  private function collection(Collection $held, Locale $locale): array
  {
    $folder = $locale->tag . '/' . $held->slug->value;
    $entries = [];
    for ($page = 1; ; $page++) {
      $query = $this->filter->query(new Query($held->type, locale: $locale, page: $page,
        perPage: Query::MAX_PER_PAGE), $held);
      if ($query === null) {
        return $entries;
      }
      $formats = $this->serialisers($locale);
      $members = $this->entities->list($query);
      foreach ($members as $member) {
        $entries[] = $this->record($member, $held, $formats, $folder);
      }
      if (!$this->listing($members, $held, $formats, $query)) {
        return $entries;
      }
    }
  }

  /* @param array<string, JsonLd|Html|Csv|Markdown> $formats
     @return array<string, mixed> the record's manifest entry, an asset's naming its file */
  private function record(Entity $entity, Collection $held, array $formats, string $folder): array
  {
    $files = [];
    foreach ($formats as $format => $serialiser) {
      $files[$format] = $format . '/' . $folder . '/' . $entity->slug->value . '.' . $format;
      $this->put($files[$format], $serialiser->record($entity, $held));
    }
    $filed = $this->filed($entity);

    return ['slug' => $entity->slug->value, 'locale' => $entity->locale->tag,
      'status' => $entity->status->value, 'type' => $entity->typeName(),
      'version' => $entity->version, 'files' => $files, ...($filed === null ? [] : ['file' => $filed[0]])];
  }

  /* Each page carries the address it is served at, pages of 200 said in it, and
     the collection's whole total, so a link into the box is a feed link with the
     prefix swapped and a reader holding one file knows how many records there were.
     @param list<Entity> $members
     @param array<string, JsonLd|Html|Csv|Markdown> $formats
     @return bool whether a page follows, so no empty one is written past the last */
  private function listing(array $members, Collection $held, array $formats, Query $query): bool
  {
    $locale = $query->locale ?? $this->settings->locales()[0];
    $first = $query->page === 1;
    $asked = ['per_page' => $query->perPage, ...($first ? [] : ['page' => $query->page])];
    $address = $this->prefix($locale) . $held->slug->value . '?' . http_build_query($asked);
    $file = $locale->tag . '/' . $held->slug->value . '/'
      . ($first ? 'index' : 'page-' . $query->page);
    $total = $this->entities->count($query);
    foreach ($formats as $format => $serialiser) {
      $this->put($format . '/' . $file . '.' . $format,
        $serialiser->listing($members, $held, $total, $address));
    }

    return $query->page * $query->perPage < $total;
  }

  /* Each file once, however many locales hold the asset.
     @return array<string, string> the path in the box to the file on disk */
  private function files(): array
  {
    $library = $this->profile->byType('MediaObject')->url();
    $files = [];
    foreach ($this->settings->locales() as $locale) {
      foreach ($this->readable() as $held) {
        foreach ($held->type === $library ? $this->assets($held, $locale) : [] as $asset) {
          $filed = $this->filed($asset);
          $filed === null || $files[$filed[0]] = $filed[1];
        }
      }
    }

    return $files;
  }

  /** @return \Generator<int, Entity> */
  private function assets(Collection $held, Locale $locale): \Generator
  {
    for ($page = 1; ; $page++) {
      $query = $this->filter->query(new Query($held->type, locale: $locale, page: $page,
        perPage: Query::MAX_PER_PAGE), $held);
      $members = $query === null ? [] : $this->entities->list($query);
      yield from $members;
      if (count($members) < Query::MAX_PER_PAGE) {
        return;
      }
    }
  }

  /* A row whose file is gone is the asset nobody has, as the address answers it.
     @return ?array{string, string} the path in the box and the file on disk */
  private function filed(Entity $entity): ?array
  {
    $extension = Media::FORMATS[(string) $entity->props->string('encoding_format')] ?? null;
    if ($entity->typeName() !== 'MediaObject' || $extension === null) {
      return null;
    }
    $name = basename((string) $entity->props->string('content_url')) . '.' . $extension;
    $file = $this->media . '/' . $name;

    return is_file($file) ? [self::FILES . $name, $file] : null;
  }

  /** @return array<string, JsonLd|Html|Csv|Markdown> */
  private function serialisers(Locale $locale): array
  {
    $addresses = new Addresses($this->profile, $this->entities, $this->filter, $locale,
      $this->origin, $this->collections->all());
    $derived = new Derivations($this->profile, $this->schema, $this->entities, $this->relations,
      $addresses, $this->clock);
    $document = new JsonLd($this->worded($locale), $this->schema, $this->entities, $this->relations,
      $this->filter, $locale, $this->origin, $addresses, $derived, built: new \ArrayObject());
    $address = fn (string $format): Address =>
      Address::inDump($this->prefix($locale), '.' . $format, $this->origin . '/media/');

    $formats = ['jsonld' => $document, 'html' => new Html($document, $address('html'), $locale),
      'csv' => new Csv($document, $address('csv')),
      'md' => new Markdown($document, $address('md'))];
    /* The page's claim is the box holding all four: a format dropped here is a
       promise broken in a folder nobody re-reads. */
    assert(array_keys($formats) === self::FORMATS, 'the dump writes the four the routes serve');

    return $formats;
  }

  /* The fields' names and the enumerations' words in the language of the
     content, which a house that leaves takes with it: nothing else holds them. */
  private function catalog(Locale $locale): Catalog
  {
    $arranged = array_filter($this->collections->all(),
      fn (Collection $held): bool => $this->authorizer->arranges($this->taker, $held));

    return new Catalog($this->worded($locale), new Variables($this->schema, $this->terms), $this->filter,
      $this->origin, array_values(array_map(fn (Collection $held): string => $held->slug->value, $arranged)));
  }

  private function worded(Locale $locale): Profile
  {
    return $this->profile->worded($this->words->fields($locale->tag));
  }

  private function prefix(Locale $locale): string
  {
    return $this->origin . '/feed/' . $locale->tag . '/';
  }

  /** @param list<array<string, mixed>> $entries */
  private function manifest(array $entries): array
  {
    return ['@context' => 'https://schema.org', '@type' => 'Dataset',
      'name' => 'Skemik export', 'dateCreated' => $this->clock->now()->format(Clock::STORED),
      'creator' => ['@type' => 'Person', 'name' => $this->actor],
      'scope' => $this->taker->read->stored() ?? 'every type',
      'authoritative' => self::AUTHORITATIVE, 'records' => $entries];
  }

  private function put(string $path, string $body): void
  {
    /* `Address` writes one `../` into every link in the box, which is right only
       while every document sits at `<format>/<locale>/<collection>/`; a catalog
       and the manifest link to no file. */
    assert(substr_count($path, '/') === 3 || str_ends_with($path, '/index.jsonld')
      || $path === 'manifest.jsonld', 'a document of the dump sits two folders under its format tree');
    $this->box->add($path, $body);
  }
}
