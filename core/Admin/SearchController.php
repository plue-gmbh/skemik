<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Relation;
use Domain\Slug;
use Domain\TranslationGroup;
use Feed\Targets;
use Http\Request;
use Http\Response;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RelationRepository;
use Storage\Search;
use Support\Json;

/* `GET /search?q=` — what Insert link and a picker offer: records across
   every collection this account may read, matched as the feed's `?q=` matches,
   best first. With `collection` and `field` it is that field's picker: only the
   types the field points at, images alone on an image field, and with nothing typed the newest of them, so a
   short list is picked from without a word — but a field pointing at any type
   waits for one. With `type` it offers that type alone, as the accounts card's
   people are offered. `record` and `held` name the places of the field's edges a
   picker still lists, whose targets it is not offered again. A match is asked of
   the fields the reader reads, a hit passes the reader's scope before it is named, and
   a record no collection holds is not offered, since the screen could not open it.
   `in` is the locale of the record being edited: a work is offered once for all
   its languages, as its translation there, and a labelled thing in its words
   there, so the picker names what the chip and the document will name. */
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
    private RelationRepository $relations,
    private Authorizer $authorizer,
    private Label $label = new Label(),
    private Json $json = new Json(),
  ) {}

  public function hits(Account $account, Request $request): Response
  {
    $house = $this->collections->all();
    $field = $this->field($request);
    $targets = $this->accepted($field, $request->query('type'));
    $in = trim((string) $request->query('in')) === '' ? null : new Locale((string) $request->query('in'));
    [$hits, $seen] = [[], array_fill_keys($this->held($account, $request, $field), true)];
    foreach ($this->found($account, $request, $targets) as $id) {
      $entity = $this->entities->find($id);
      if (!$this->fits($field, $targets, $entity) || !$this->rows->readable($account, $entity)) {
        continue;
      }
      $offered = $this->offered($account, $entity, $in);
      $root = TranslationGroup::rootOf($offered)->value;
      if (!isset($seen[$root]) && ($hit = $this->hit($offered, $house, $in)) !== null) {
        [$seen[$root], $hits[]] = [true, $hit];
      }
      if (count($hits) === self::HITS) {
        break;
      }
    }

    return Response::json($this->json->encode(['hits' => $hits]));
  }

  /** @param ?list<string> $targets null for any type
      @return list<EntityId> best first */
  private function found(Account $account, Request $request, ?array $targets): array
  {
    $house = $this->collections->all();
    $typed = (string) $request->query('q');

    return trim($typed) === '' && $targets !== null ? $this->newest($house, $targets, $this->field($request))
      : $this->search->search($typed, $this->readings($account, $house, $targets));
  }

  /* The targets the field holds at the places the picker still lists, each as its
     group's root, since a hit stands for its group: a picker names a held target by
     its place and never by its id (`Chip::held`), so what it holds is read here. Only
     for a staff reader of the record's type, who reads every field of it and is the
     only one with a picker, writing being within reading: for anybody else the hits
     missing would tell of an edge the feed withholds.
     @return list<string> */
  private function held(Account $account, Request $request, ?FieldDefinition $field): array
  {
    [$record, $places] = [$request->query('record'), (string) $request->query('held')];
    if ($field === null || $record === null || $places === '') {
      return [];
    }
    $subject = $this->entities->find(new EntityId($record));
    if ($this->authorizer->readsAsPublic($account, $subject->type)) {
      return [];
    }
    $predicate = (string) $this->schema->propertyName($field->key);
    $mine = array_values(array_filter($this->relations->outbound($subject->id),
      fn (Relation $edge): bool => $edge->predicate === $predicate));
    $edges = array_filter(array_map(fn (string $place): ?Relation => $mine[(int) $place] ?? null,
      explode(',', $places)));

    return array_values(array_filter(array_map($this->rootOf(...), $edges)));
  }

  /* A target that is gone is offered by nobody, so it excludes nothing. */
  private function rootOf(Relation $edge): ?string
  {
    try {
      return TranslationGroup::rootOf($this->entities->find($edge->object))->value;
    } catch (EntityNotFound) {
      return null;
    }
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

  /* Each type the search may offer, as this account reads it, since a match on
     any other field would name a record by a word the reader is not shown.
     @param list<Collection> $house
     @param ?list<string> $targets null for any type
     @return array<string, Query> */
  private function readings(Account $account, array $house, ?array $targets): array
  {
    $offered = array_filter($house, fn (Collection $collection): bool
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

  /* @return ?list<string> the type asked for, or the types an edge points at or a
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

  /* A field is offered what a save of it takes (`Feed\Targets::misfit`).
     @param ?list<string> $targets null for any type */
  private function fits(?FieldDefinition $field, ?array $targets, Entity $entity): bool
  {
    return ($field === null || $this->targets->misfit($field, $entity) === null)
      && ($targets === null || in_array($entity->typeName(), $targets, true));
  }

  /**
   * @param list<Collection> $house
   * @param list<string> $targets
   * @return list<EntityId> newest first
   */
  private function newest(array $house, array $targets, ?FieldDefinition $field): array
  {
    $types = array_unique(array_map(fn (Collection $collection): string => $collection->type, $house));
    $found = [];
    foreach ($types as $type) {
      if (in_array($this->profile->byType($type)->name, $targets, true)) {
        $found = [...$found, ...$this->entities->list(new Query($type, $this->narrowed($field)))];
      }
    }
    usort($found, fn (Entity $a, Entity $b): int => strcmp($b->createdAt, $a->createdAt));

    return array_map(fn (Entity $entity): EntityId => $entity->id, $found);
  }

  /* What a field's picker opens on is asked of the holdings and not sifted from
     the newest of the type, or a set of three terms opens empty behind fifty
     newer ones of another set, and a picture behind fifty documents.
     @return array<string, list<string>> */
  private function narrowed(?FieldDefinition $field): array
  {
    $root = $field?->cardinality === Cardinality::T ? $this->targets->root($this->schema->termSet($field)) : null;
    $kinds = $field === null ? [] : $this->schema->fileKinds($field->targets);

    return match (true) {
      $root !== null => ['in_defined_term_set' => [$root->id->value]],
      $kinds !== [] => ['encoding_format' => array_map(fn (string $kind): string => $kind . '/', $kinds)],
      default => [],
    };
  }

  /* The slug beside the label, since two offers may both be called Ticket, an
     image's preview, since two photos are told apart by looking, and a work's
     locale only where it is not the one edited, so a German page offered on an
     English site says so, and its type, since an edge to a `MemberProgram` says
     more than one to anything else.
     @param list<Collection> $house
     @return ?array{id: string, label: string, slug: string, glyph: string, collection: string,
       preview: ?string, locale: ?string, type: string} null for a record no collection holds */
  private function hit(Entity $entity, array $house, ?Locale $in): ?array
  {
    $holder = array_find($house, fn (Collection $collection): bool => $collection->type === $entity->type);
    if ($holder === null) {
      return null;
    }
    $type = $this->profile->byType($entity->type);
    $shown = $in === null ? $entity : (new LocalisedText($type))->shown($entity, $in);
    $elsewhere = $in !== null && $type->locale->forks() && $entity->locale->tag !== $in->tag;

    return ['id' => $entity->id->value, 'label' => $this->label->of($shown, $type),
      'slug' => $entity->slug->value, 'glyph' => SidebarEntry::glyphOf($entity->type),
      'collection' => $holder->name, 'preview' => Rows::preview($entity),
      'locale' => $elsewhere ? $entity->locale->tag : null, 'type' => $entity->typeName()];
  }
}
