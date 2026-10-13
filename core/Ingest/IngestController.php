<?php

declare(strict_types=1);

namespace Ingest;

use Auth\Authorizer;
use Auth\GrantCapability;
use Auth\Token;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\StaleWrite;
use Domain\Exceptions\ValueTaken;
use Domain\Locale;
use Domain\Props;
use Domain\Slug;
use Domain\Violation;
use Feed\FeedController;
use Http\Preconditions;
use Http\Request;
use Http\Response;
use Http\Route;
use Http\TooManyRequests;
use Profile\LocaleMode;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Profile\Validator;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\Collisions;
use Storage\EntityRepository;
use Storage\RateLimit;
use Storage\RelationRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\JsonFailed;
use Support\Uuid;

/* The write side of the address. The key is asked before anything is looked up,
   and a rule on the collection before the record, so a caller without one cannot
   tell an address that exists from one that does not. A write is one record, one
   tag and one answer, since a batch refused half way leaves the channel to find
   out which half. A new record lands as a draft, since custody never travels in a
   document. The same order arriving twice answers the order as held and writes
   nothing, `200` rather than `201`, so a retrying channel cannot undo a
   colleague's correction. */
final readonly class IngestController
{
  /** @param \Closure(Token): EntityRepository $entitiesFor the writes, logged under the token making them */
  public function __construct(
    private Authorizer $authorizer,
    private FeedController $feed,
    private CollectionStore $collections,
    private SettingStore $settings,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private Collisions $collisions,
    private PayloadMapper $mapper,
    private Validator $validator,
    private Profile $profile,
    private SchemaOrg $schema,
    private RateLimit $perToken,
    private RateLimit $perClient,
    private Uuid $uuid,
    private Clock $clock,
    private \Closure $entitiesFor,
    private Filed $filed,
    private Preconditions $preconditions = new Preconditions(),
    private Json $json = new Json(),
  ) {}

  public function create(Request $request, Route $route): Response
  {
    $collection = $this->admitted($request, $route);
    $upload = $request->upload;
    $document = $upload === null ? $this->document($request)
      : $this->filed->document($collection, $this->document($request));
    $token = $this->authorizer->requireMachine($collection, $this->mapper->fields($collection, $document));
    $spoken = $this->spoken($collection, $route, $document);
    $draft = $this->draft($collection, $this->locale($spoken));
    $payload = $this->mapper->map($spoken, $draft->id, $document);
    $holder = $this->holder($draft, $payload);
    if ($holder !== null) {
      return $this->feed->document($request, $route, $holder);
    }
    $write = fn (Entity $record): Entity|Response => $this->write($token, $record, $payload);
    $saved = $upload === null ? $write($draft) : $this->filed->written($upload, $draft, $write);

    return $saved instanceof Response ? $saved : $this->created($request, $route, $saved);
  }

  /* The language of a photograph's or a dataset's bytes is read alike on every
     route (`Profile\LocaleMode`), so a new one is written as on the route of the
     language its document states, where the house holds it: posted back from
     another tree it is the record it was read as.
     @param array<string, mixed> $document */
  private function spoken(Collection $collection, Route $route, array $document): Route
  {
    $stated = $document['inLanguage'] ?? null;
    $held = array_any($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $stated);
    $both = $this->profile->byType($collection->type)->locale === LocaleMode::Both;

    return $both && $held ? new Route($route->endpoint, [...$route->parameters, 'locale' => (string) $stated])
      : $route;
  }

  private function created(Request $request, Route $route, Entity $saved): Response
  {
    $answer = $this->feed->document($request, $route, $saved);
    $address = sprintf('%s/feed/%s/%s/%s', $request->origin(), $route->parameter('locale'),
      $route->parameter('collection'), $saved->slug->value);

    return new Response(201, $answer->body, [...$answer->headers, 'Location' => $address]);
  }

  public function patch(Request $request, Route $route): Response
  {
    $collection = $this->admitted($request, $route);
    if ($request->upload !== null) {
      throw MalformedValue::named('write taking a file', 'PATCH');
    }
    $document = $this->document($request);
    $this->authorizer->requireRule($collection, GrantCapability::Set);
    $record = $this->entities->bySlug($collection->type, $this->locale($route),
      $this->slugOf($route->parameter('slug')))
      ?? throw EntityNotFound::withId($route->parameter('slug'));
    $token = $this->authorizer->requireMachine($collection, $this->mapper->fields($collection, $document),
      $record);
    $expected = $this->preconditions->expectedVersion($request, $record->id);
    if ($expected !== $record->version) {
      throw StaleWrite::expected($expected);
    }
    $saved = $this->write($token, $record, $this->mapper->map($route, $record->id, $document));

    return $saved instanceof Response ? $saved : $this->feed->document($request, $route, $saved);
  }

  /* Both buckets count on every write, so a refused one does not spare the other;
     the key is asked for next, before the collection is looked up. */
  private function admitted(Request $request, Route $route): Collection
  {
    $token = $this->perToken->hit('ingest:' . hash('sha256', (string) $request->bearer()));
    $client = $this->perClient->hit('ingest:client:' . hash('sha256', $request->client));
    if (!$token || !$client) {
      throw new TooManyRequests('too many writes from this key or this address; try again in a minute');
    }
    $this->authorizer->requireToken();
    $slug = $route->parameter('collection');

    return $this->collections->bySlug($this->slugOf($slug)) ?? throw EntityNotFound::withId($slug);
  }

  /* The body, or beside a file the multipart's `document` part.
     @return array<string, mixed> */
  private function document(Request $request): array
  {
    try {
      $document = $this->json->decode($request->upload === null ? $request->body
        : (string) $request->form('document'));
    } catch (JsonFailed) {
      throw MalformedValue::notA('a JSON object', 'the body');
    }

    return is_array($document) && !array_is_list($document) ? $document
      : throw MalformedValue::notA('a JSON object', 'the body');
  }

  /* The whole record is validated after the merge, since a † is a wall on the
     fields nobody sent as much as on the ones they did; a new one takes the first
     free slug its naming field gives, and a `!` value already held is refused by
     its field like any other that does not fit.
     @return Entity|Response the record as saved, or the refusal naming each field */
  private function write(Token $token, Entity $record, Payload $payload): Entity|Response
  {
    $stored = $this->isStored($record);
    $type = $this->profile->byType($record->type);
    $written = $payload->onto($record);
    $entity = $written->withProps(new Props($type->named($record->props->document(),
      $written->props->document())));
    $edges = $payload->edges($record, $stored ? $this->relations->outbound($record->id) : []);
    $violations = [...$payload->refusedOn($record), ...$this->grouped($entity),
      ...$this->validator->check($type, $entity->props->document(), $edges)];
    if ($violations !== []) {
      return $this->refused($type, $violations);
    }
    $entities = ($this->entitiesFor)($token);
    try {
      return $stored ? $entities->save($entity, $edges, $record->version)
        : $entities->save($this->named($entity, $type), $edges);
    } catch (ValueTaken $taken) {
      return $this->refused($type, [$taken->violation]);
    }
  }

  /* A translation joins a group of its own type, in a locale the group has free,
     asked here as well as in the save so the refusal names the field.
     @return list<Violation> */
  private function grouped(Entity $entity): array
  {
    if ($entity->translationOf === null) {
      return [];
    }
    try {
      $this->collisions->requireFreeTranslation($entity);
    } catch (MalformedValue | EntityNotFound $refusal) {
      return [new Violation('translationOfWork', $refusal->getMessage())];
    } catch (ValueTaken $taken) {
      return [new Violation('translationOfWork', $taken->violation->reason)];
    }

    return [];
  }

  private function named(Entity $entity, TypeDefinition $type): Entity
  {
    return $entity->withSlug(Slug::from((string) $entity->props->string($type->namedBy()), $entity->slug->value));
  }

  private function isStored(Entity $record): bool
  {
    try {
      $this->entities->find($record->id);

      return true;
    } catch (EntityNotFound) {
      return false;
    }
  }

  /* A create repeating a `(seller, orderNumber)` is the order arriving twice,
     which is why the number is unique per seller; every other `!` is refused. */
  private function holder(Entity $draft, Payload $payload): ?Entity
  {
    if ($draft->typeName() !== 'Order') {
      return null;
    }
    $holder = $this->collisions->holder($payload->onto($draft), $payload->edges($draft, []));

    return $holder === null ? null : $this->entities->find($holder);
  }

  private function draft(Collection $collection, Locale $locale): Entity
  {
    $type = $this->profile->byType($collection->type);
    $now = $this->clock->now()->format(Clock::STORED);

    return new Entity(new EntityId($this->uuid->generate()), $type->url(), $locale,
      Slug::from($type->name, 'record'), new Props([]), $now, $now);
  }

  /* Each field once, as the caller wrote it and never as it is stored: what the
     document could not say is named before what it said that does not fit.
     @param list<Violation> $violations */
  private function refused(TypeDefinition $type, array $violations): Response
  {
    $said = [];
    foreach ($violations as $violation) {
      $said[$this->schema->spelt($type, $violation->field)] ??= $violation->reason;
    }
    $listed = array_map(fn (string $field, string $reason): array => ['field' => $field, 'reason' => $reason],
      array_keys($said), $said);

    return Response::json($this->json->encode(['error' => 'the record does not fit its type',
      'violations' => $listed]), 422);
  }

  /* A locale the house does not hold is no address of this install. */
  private function locale(Route $route): Locale
  {
    $tag = $route->parameter('locale');

    return array_find($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw EntityNotFound::withId($tag);
  }

  private function slugOf(string $value): Slug
  {
    try {
      return new Slug($value);
    } catch (MalformedValue) {
      throw EntityNotFound::withId($value);
    }
  }
}
