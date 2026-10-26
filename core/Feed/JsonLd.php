<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Relation;
use Domain\TranslationGroup;
use Profile\Cardinality;
use Profile\Derived;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Seed\Media;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionField;
use Storage\EntityRepository;
use Storage\RelationRepository;
use Support\Json;
use Support\Markup;
use Support\Rendered;

/* JSON-LD straight from `props`, the payoff of the whole storage model, built for
   one caller's reading, so no document can be rendered for somebody else. A list
   is named by its type, the same in every house. A member is wrapped whatever it
   carries, so the wire shape never follows the content. Edge depth is 1 and
   there is no `expand` parameter: a nested object is a rendering of a record and
   not the record, so only a document fetched at its own address may be cached
   under that address. A dump hands it `$built` for a page, whose records it
   writes in four formats and again as the listing, so each is built once per
   spelling, the JSON-LD's (`record`) and the flat formats', and the HTML page's
   again with its texts rendered (`rendering`). */
final readonly class JsonLd
{
  public const CONTENT_TYPE = 'application/ld+json; charset=utf-8';
  private const CONTEXT = 'https://schema.org';
  private const SPINE = ['inLanguage', 'translationOfWork', 'workTranslation'];

  private Properties $properties;
  private Spelling $spelling;

  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private PublicFilter $filter,
    private Locale $locale,
    private string $origin,
    private Addresses $addresses,
    private Derivations $derived,
    private Json $json = new Json(),
    ?Markup $markup = null,
    private ?\ArrayObject $built = null,
    private bool $addressed = false,
  ) {
    $this->properties = new Properties($profile, $schema, $markup);
    $this->spelling = new Spelling($schema, $profile, $origin, $addressed);
  }

  public function rendering(Markup $markup): self
  {
    return clone($this, ['properties' => new Properties($this->profile, $this->schema, $markup), 'built' => null]);
  }

  public function contentType(): string
  {
    return self::CONTENT_TYPE;
  }

  /* The JSON answers spell a fixed list's value as the member's address
     (`SchemaOrg::member`); the flat formats, built on the documents below, keep
     the token, which is what a filter names and a table sorts. */
  public function record(Entity $entity, Collection $collection): string
  {
    return $this->json->encode($this->spelling()->recordDocument($entity, $collection));
  }

  /** @param list<Entity> $members this page; $total every record the query matches */
  public function listing(array $members, Collection $collection, int $total, string $address): string
  {
    return $this->json->encode($this->spelling()->listDocument($members, $collection, $total, $address));
  }

  /* The same cache, whose keys say the spelling, so a page's listing reuses what its records built. */
  private function spelling(): self
  {
    return $this->addressed ? $this : clone($this, ['addressed' => true,
      'spelling' => new Spelling($this->schema, $this->profile, $this->origin, true)]);
  }

  /* The flat formats are this document in another syntax, so the gates run once
     and no serialiser can be the one that forgets them. @return array<string, mixed> */
  public function recordDocument(Entity $entity, Collection $collection): array
  {
    return ['@context' => self::CONTEXT, ...$this->document($entity, $collection, Depth::Addressed)];
  }

  /** @return array<string, mixed> */
  public function listDocument(array $members, Collection $collection, int $total, string $address): array
  {
    $items = array_map(fn (Entity $member, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1,
      'item' => $this->document($member, $collection, Depth::Addressed)], $members, array_keys($members));

    return ['@context' => self::CONTEXT, '@id' => $address, '@type' => 'ItemList',
      'name' => basename($collection->type), 'numberOfItems' => $total, 'itemListElement' => $items];
  }

  /** @return array<string, mixed> */
  private function document(Entity $entity, Collection $collection, Depth $depth): array
  {
    $key = implode(' ', [$entity->id->value, $entity->version, $collection->slug->value, $depth->name,
      $this->addressed ? 'addressed' : 'token']);

    return $this->built === null ? $this->build($entity, $collection, $depth)
      : ($this->built[$key] ??= $this->build($entity, $collection, $depth));
  }

  /** @return array<string, mixed> */
  private function build(Entity $entity, Collection $collection, Depth $depth): array
  {
    $type = $this->profile->byType($entity->type);
    $below = $depth->below($entity->typeName() === 'ItemList');
    $edges = $this->relations->outboundByPredicate($entity->id);
    $shown = (new LocalisedText($type))->shown($entity, $this->locale);
    $values = [];
    foreach ($this->filter->fields($collection) as $held) {
      $field = $type->field($held->key);
      $values[$held->key] = match (true) {
        $field === null => null,
        $field->derivation?->function === Derived::Range => $this->derived->offerRanges($entity, $field),
        $this->derived->derives($entity, $field) => $this->derived->value($entity, $field),
        $field->cardinality->isEdge() => $this->edge($field, $edges, $below),
        $field->kind === FieldKind::Markdown => $this->addresses->linked($shown->props->string($field->key)),
        default => $this->scalar($shown, $field, $below),
      };
    }

    return ['@id' => $this->addresses->in($collection, $entity), '@type' => Media::emittedType($entity),
      ...$this->properties->of($type, $values), ...$this->spine($entity, $type)];
  }

  /* What each property is called in the content's language, for a page that
     shows a person the values; a document itself carries no label.
     @return array<string, string> */
  public function labels(Collection $collection): array
  {
    return $this->properties->labels($collection->type);
  }

  /* Every property a document of this reading may carry, in its order, so a
     table has the same columns on every page whatever its rows hold.
     @return list<string> */
  public function columns(Collection $collection): array
  {
    $keys = array_map(fn (CollectionField $held): string => $held->key, $this->filter->fields($collection));
    $spine = $this->profile->byType($collection->type)->locale->forks() ? self::SPINE : [];

    return ['@id', ...$this->properties->names($collection->type, $keys), ...$spine];
  }

  /* A plain value as the document shows it (`Spelling`), a referring key as its
     target, and rows each with theirs.
     @return string|int|float|bool|array<array-key, mixed>|null */
  private function scalar(Entity $entity, FieldDefinition $field, ?Depth $below): string|int|float|bool|array|null
  {
    $value = $entity->props->document()[$field->key] ?? null;
    $key = (string) $this->profile->referring($field)?->reference;
    if ($field->valueObject !== null && $key !== '' && $value !== null) {
      return $this->referring($field, [$key => $value], $below)[$key] ?? null;
    }
    if ($field->kind !== FieldKind::Objects) {
      return $this->spelling->shown($field, $value);
    }
    /** @param array<string, mixed> $row */
    $typed = fn (array $row): array => ['@type' => (string) $field->ref,
      ...$this->spelling->row($field, $this->referring($field, $row, $below))];

    return array_map($typed, array_filter($entity->props->list($field->key), is_array(...)));
  }

  /* A row's reference, or a flattened key's, leaves as any target does, through
     the same gates and at the same depth, or not at all; a word leaves as a
     thing of that name, so a consumer reads `name` either way, unless the key
     is a link, where the word is an address and leaves as one.
     @param array<string, mixed> $row
     @return array<string, mixed> */
  private function referring(FieldDefinition $field, array $row, ?Depth $below): array
  {
    $object = $this->profile->referring($field);
    $key = $object?->reference;
    if ($object === null || $key === null || !isset($row[$key])) {
      return $row;
    }
    $id = $object->referredBy($row);
    $linked = ($object->kinds[$key] ?? null) === FieldKind::Link;
    $row[$key] = match (true) {
      $id !== null => $this->target(new EntityId($id), $below),
      $linked => $row[$key],
      default => ['@type' => $object->wordType(), 'name' => $row[$key]],
    };

    return array_filter($row, fn (mixed $value): bool => $value !== null);
  }

  /* A record among its own targets, a live list that lists itself, is its
     address alone: rendered, it would carry the document once more inside it.
     @param array<string, list<Relation>> $edges the record's, by predicate, in their stored order
     @return array<array-key, mixed>|string|Rendered|null */
  private function edge(FieldDefinition $field, array $edges, ?Depth $below): array|string|Rendered|null
  {
    $values = [];
    foreach ($edges[(string) $this->schema->propertyName($field->key)] ?? [] as $relation) {
      $itself = $relation->object->value === $relation->subject->value;
      $target = $this->targetOf($field, $relation, $itself ? null : $below);
      if ($target !== null) {
        $values[] = $this->wrapped($relation, $target);
      }
    }

    return $field->holdsMany() ? $values : ($values[0] ?? null);
  }

  /* A term by its code and any other record as its own document one level down
     — or nothing at all where it fails the gates, since an `@id` carries the
     slug and a slug is a name.
     @return array<string, mixed>|string|Rendered|null */
  private function targetOf(FieldDefinition $field, Relation $edge, ?Depth $below): array|string|Rendered|null
  {
    $target = $this->target($edge->object, $below);

    return $target !== null && $field->cardinality === Cardinality::T
      ? $this->properties->code((string) $edge->objectTerm) : $target;
  }

  /** @return ?array<string, mixed> */
  private function target(EntityId $id, ?Depth $below): ?array
  {
    try {
      $target = $this->addresses->inReadersLocale($this->entities->find($id));
    } catch (EntityNotFound) {
      return null;
    }
    $collection = $this->addresses->collectionOf($target);
    if ($collection === null) {
      return null;
    }

    return $below === null ? ['@id' => $this->addresses->in($collection, $target)]
      : $this->document($target, $collection, $below);
  }

  /* An edge's qualifiers are what this surface says about the target, in the reader's language.
     @param array<string, mixed>|string|Rendered $target
     @return array<string, mixed>|string|Rendered */
  private function wrapped(Relation $edge, array|string|Rendered $target): array|string|Rendered
  {
    return (new Wrappers())->wrapped($edge, $this->properties->qualified($edge->qualifiersIn($this->locale)),
      $target);
  }

  /* The spine's content columns, which survive a copy of the holdings, in
     schema.org's vocabulary and never under spine names. A labelled thing emits
     no `inLanguage`: a price written in German would announce who typed it
     first. @return array<string, mixed> */
  private function spine(Entity $entity, TypeDefinition $type): array
  {
    if (!$type->locale->forks()) {
      return [];
    }
    $root = TranslationGroup::rootOf($entity)->value;
    $translations = [];
    foreach ($this->entities->group($entity)->others($entity) as $other) {
      $address = $this->addresses->of($other);
      if ($address !== null) {
        $translations[$other->id->value === $root ? 'translationOfWork' : 'workTranslation'][] = ['@id' => $address];
      }
    }

    return ['inLanguage' => $entity->locale->tag, ...$translations];
  }
}
