<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Slug;
use Domain\TranslationGroup;
use Feed\Targets;
use Http\Request;
use Http\Response;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\Search;
use Support\Json;

/* `GET /search?q=`: records across every collection this account may read,
   matched as the feed's `?q=` matches, best first, for Insert link and the
   pickers. With `collection` and `field` it is that field's picker and offers
   only the types the field points at, images alone on an image field, and the
   newest of them while nothing is typed, so a short list is picked from without
   a word; a field pointing at any type waits for one. `record` and `held` name
   the edges a picker still lists, so their targets are not offered again. A hit
   passes the reader's scope before it is named, and a record no collection
   holds is not offered, since the screen could not open it. `in` is the edited
   record's locale, so the picker names what the chip and the document will. */
final readonly class SearchController
{
  private const HITS = 8;

  public function __construct(
    private Search $search,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private Rows $rows,
    private Profile $profile,
    private SchemaOrg $schema,
    private Targets $targets,
    private HeldTargets $held,
    private Label $label = new Label(),
    private Json $json = new Json(),
  ) {}

  public function hits(Account $account, Request $request): Response
  {
    $collections = $this->collections->all();
    $field = $this->field($request);
    $in = trim((string) $request->query('in')) === '' ? null : new Locale((string) $request->query('in'));
    $hits = [];
    $seen = array_fill_keys($this->held->roots($account, $request, $field), true);
    foreach ($this->fitting($account, $request, $field) as $entity) {
      $offered = $this->offered($account, $entity, $in);
      $root = TranslationGroup::rootOf($offered)->value;
      if (!isset($seen[$root]) && ($hit = $this->hit($offered, $collections, $in)) !== null) {
        $seen[$root] = true;
        $hits[] = [...$hit, 'preview' => $this->rows->pictured($account, $offered)];
      }
      if (count($hits) === self::HITS) {
        break;
      }
    }

    return Response::json($this->json->encode(['hits' => $hits]));
  }

  /** What may be offered, best first: of the types asked, what the field takes
      and this account reads; a link's search is the library's files of the
      kinds the link takes.
      @return \Generator<int, Entity> */
  private function fitting(Account $account, Request $request, ?FieldDefinition $field): \Generator
  {
    $kinds = $this->linked($request, $field);
    $targets = $kinds === null ? $this->accepted($field, $request->query('type')) : ['MediaObject'];
    foreach ($this->found($account, $request, $targets) as $id) {
      $entity = $this->entities->find($id);
      $kind = (string) strtok((string) $entity->props->string('encoding_format'), '/');
      $fits = $kinds === null ? $this->fits($field, $targets, $entity) : $kinds === [] || in_array($kind, $kinds, true);
      if ($fits && $this->rows->readable($account, $entity)) {
        yield $entity;
      }
    }
  }

  /** The kinds of file the link searched takes (`SchemaOrg::linkTakes`): a link
      field's, or with `key` a row's link key's; null where the search is no link's.
      @return ?list<string> */
  private function linked(Request $request, ?FieldDefinition $field): ?array
  {
    $key = $request->query('key');
    if ($field === null || ($key === null && $field->kind !== FieldKind::Link)) {
      return null;
    }
    $kinds = $key === null ? [] : $this->profile->valueObject((string) $field->ref)?->kinds ?? [];
    if ($key !== null && ($kinds[$key] ?? null) !== FieldKind::Link) {
      throw MalformedValue::named('link key of ' . $field->key, $key);
    }
    $property = $key ?? (string) $this->schema->propertyName($field->key);

    return $this->schema->linkTakes($property) ?? throw MalformedValue::named('link taking a file', $property);
  }

  /** @param ?list<string> $targets null for any type
      @return list<EntityId> best first */
  private function found(Account $account, Request $request, ?array $targets): array
  {
    $collections = $this->collections->all();
    $typed = (string) $request->query('q');

    return trim($typed) === '' && $targets !== null ? $this->newest($collections, $targets, $this->narrowed($request))
      : $this->search->search($typed, $this->readings($account, $collections, $targets));
  }

  /* A work stands for its whole group: its translation in the locale edited
     where the reader may read one, else its root, else the one found. */
  private function offered(Account $account, Entity $found, ?Locale $in): Entity
  {
    if (!$this->profile->byType($found->type)->locale->forks()) {
      return $found;
    }
    $group = $this->entities->group($found);
    $root = array_find($group->members, fn (Entity $member): bool => $member->id->value === $group->root->value);
    foreach (array_filter([$in === null ? null : $group->inLocale($in), $root]) as $candidate) {
      if ($this->rows->readable($account, $candidate)) {
        return $candidate;
      }
    }

    return $found;
  }

  /** Each type the search may offer, as this account reads it, since a match on
      any other field would name a record by a word the reader is not shown.
      @param list<Collection> $collections
      @param ?list<string> $targets null for any type
      @return array<string, Query> */
  private function readings(Account $account, array $collections, ?array $targets): array
  {
    $offered = array_filter($collections, fn (Collection $collection): bool
      => $targets === null || in_array(basename($collection->type), $targets, true));

    return $this->rows->readings($account, array_values($offered));
  }

  /* The field a picker searches for; null for the link search. */
  private function field(Request $request): ?FieldDefinition
  {
    $slug = $request->query('collection');
    if ($slug === null) {
      return null;
    }
    $collection = $this->collections->bySlug(new Slug($slug)) ?? throw EntityNotFound::withId($slug);
    $key = (string) $request->query('field');

    return $this->profile->byType($collection->type)->field($key)
      ?? throw MalformedValue::named('field of ' . $collection->name, $key);
  }

  /** @return ?list<string> the type asked for, or the types an edge points at or a
      value object's row refers to, a branch among the organisations; null for any type */
  private function accepted(?FieldDefinition $field, ?string $type): ?array
  {
    if ($type !== null) {
      return $this->profile->has($type) ? [$this->profile->byType($type)->name]
        : throw MalformedValue::named('type', $type);
    }
    $targets = $field === null ? null : $this->profile->referring($field)?->targets ?? $field->targets;
    if ($targets === null || $targets === ['*']) {
      return null;
    }
    $names = array_map(fn (TypeDefinition $type): string => $type->name, $this->profile->types());

    return array_values(array_filter($names,
      fn (string $name): bool => $this->schema->takes($targets, $name)));
  }

  /** A field is offered what a save of it takes (`Feed\Targets::misfit`).
      @param ?list<string> $targets null for any type */
  private function fits(?FieldDefinition $field, ?array $targets, Entity $entity): bool
  {
    return ($field === null || $this->targets->misfit($field, $entity) === null)
      && ($targets === null || in_array($entity->typeName(), $targets, true));
  }

  /** @param list<Collection> $collections @param list<string> $targets @param array<string, list<string>> $narrowed
      @return list<EntityId> newest first */
  private function newest(array $collections, array $targets, array $narrowed): array
  {
    $types = array_unique(array_map(fn (Collection $collection): string => $collection->type, $collections));
    $found = [];
    foreach ($types as $type) {
      if (in_array($this->profile->byType($type)->name, $targets, true)) {
        $found = [...$found, ...$this->entities->list(new Query($type, $narrowed))];
      }
    }
    usort($found, fn (Entity $a, Entity $b): int => strcmp($b->createdAt, $a->createdAt));

    return array_map(fn (Entity $entity): EntityId => $entity->id, $found);
  }

  /** What a field's picker opens on is asked of the holdings and not sifted from
      the newest of the type, or a set of three terms opens empty behind fifty
      newer ones of another set, and a picture behind fifty documents.
      @return array<string, list<string>> */
  private function narrowed(Request $request): array
  {
    $field = $this->field($request);
    $root = $field?->cardinality === Cardinality::T ? $this->targets->root($this->schema->termSet($field)) : null;
    $kinds = $this->linked($request, $field) ?? ($field === null ? [] : $this->schema->fileKinds($field->targets));

    return match (true) {
      $root !== null => ['in_defined_term_set' => [$root->id->value]],
      $kinds !== [] => ['encoding_format' => array_map(fn (string $kind): string => $kind . '/', $kinds)],
      default => [],
    };
  }

  /** The slug beside the label, since two offers may both be called Ticket, a
      work's locale only where it is not the one edited, so a German page offered
      on an English site says so, and its type, since an edge to a `MemberProgram`
      says more than one to anything else; its picture, since two photos are told
      apart by looking, is `hits`' to add, being the reader's.
      @param list<Collection> $collections
      @return ?array{id: string, label: string, slug: string, glyph: string, collection: string,
        locale: ?string, type: string, file: ?array{address: string, format: string, size: string}}
        null for a record no collection holds */
  private function hit(Entity $entity, array $collections, ?Locale $in): ?array
  {
    $holder = array_find($collections, fn (Collection $collection): bool => $collection->type === $entity->type);
    if ($holder === null) {
      return null;
    }
    $type = $this->profile->byType($entity->type);
    $shown = $in === null ? $entity : (new LocalisedText($type))->shown($entity, $in);
    $elsewhere = $in !== null && $type->locale->forks() && $entity->locale->tag !== $in->tag;

    return ['id' => $entity->id->value, 'label' => $this->label->of($shown, $type),
      'slug' => $entity->slug->value, 'glyph' => SidebarEntry::glyphOf($entity->type),
      'collection' => $holder->name, 'locale' => $elsewhere ? $entity->locale->tag : null,
      'type' => $entity->typeName(), 'file' => Rows::file($entity)];
  }
}
