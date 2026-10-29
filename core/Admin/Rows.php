<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Locale;
use Domain\LocalisedText;
use Feed\PublicFilter;
use Profile\Cardinality;
use Profile\FieldKind;
use Profile\Profile;
use Profile\SchemaOrg;
use Storage\Collection;
use Storage\CollectionField;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RelationRepository;
use Support\Clock;

/* A row's edges as chips, per field key, a reference's with its edge for the
   words it says; a term's word is its record's. Every target passes the
   reader's scope first: outside it a target is read as the public reads it, and
   one that fails is drawn as gone, since a chip naming it would be the
   disclosure the record was not. A target in the trash is drawn gone as well, and a save sends its
   chip back and keeps the edge, so restoring the target brings every edge at it
   back, and taking the chip out is the one way to let one go. One lookup per
   target; a page of fifty rows with a handful of edges each is what this is
   sized for. */
final readonly class Rows
{
  public function __construct(
    private RelationRepository $relations,
    private EntityRepository $entities,
    private Authorizer $authorizer,
    private PublicFilter $filter,
    private Profile $profile,
    private SchemaOrg $schema,
    private Clock $clock,
    private Label $label = new Label(),
  ) {}

  /**
   * @param list<Collection> $collections the house's, for a target's glyph and name
   * @return array<string, list<Chip>> by field key, in edge order
   */
  public function chips(Account $account, Entity $entity, array $collections): array
  {
    $fields = [];
    foreach ($this->profile->byType($entity->type)->edges() as $field) {
      $fields[(string) $this->schema->propertyName($field->key)] = $field;
    }
    $draw = fn (EntityId $id): Chip
      => $this->drawn($this->target($account, $id, $entity->locale), $entity->locale, $collections);
    $chips = array_map(fn (array $ids): array => array_map($draw, $ids), $this->referred($entity));
    foreach ($this->relations->outbound($entity->id) as $edge) {
      $field = $fields[$edge->predicate] ?? null;
      if ($field !== null) {
        $chip = $draw($edge->object);
        $chips[$field->key][] = $field->cardinality === Cardinality::E ? $chip->saying($edge) : $chip;
      }
    }

    return $chips;
  }

  /* The records a value object's rows refer to, by field key and row, and the
     one a flattened key refers to as its row 0, drawn as an edge's target is.
     @return array<string, array<int, EntityId>> */
  private function referred(Entity $entity): array
  {
    $ids = [];
    $document = $entity->props->document();
    foreach ($this->profile->byType($entity->type)->fields() as $field) {
      $object = $this->profile->referring($field);
      $rows = $field->kind === FieldKind::Objects ? $entity->props->list($field->key)
        : [[(string) $object?->reference => $document[$field->key] ?? null]];
      foreach ($object === null ? [] : $rows as $index => $row) {
        $id = $object->referredBy($row);
        if ($id !== null) {
          $ids[$field->key][$index] = new EntityId($id);
        }
      }
    }

    return $ids;
  }

  /* Null for a target gone, trashed or out of the reader's scope. A work is its
     translation in the locale it is read from where the reader may read one,
     as the feed names it on that locale's route (`Feed\Addresses`). */
  private function target(Account $account, EntityId $id, Locale $in): ?Entity
  {
    try {
      $target = $this->entities->find($id);
    } catch (EntityNotFound) {
      return null;
    }
    if ($target->deletedAt !== null || !$this->readable($account, $target)) {
      return null;
    }
    $elsewhere = $target->locale->tag !== $in->tag && $this->profile->byType($target->type)->locale->forks();
    $translation = $elsewhere ? $this->entities->group($target)->inLocale($in) : null;

    return $translation !== null && $this->readable($account, $translation) ? $translation : $target;
  }

  /* Named in the words of the locale it is read from, as the feed names it on
     that locale's route.
     @param list<Collection> $collections */
  private function drawn(?Entity $target, Locale $in, array $collections): Chip
  {
    if ($target === null) {
      return Chip::gone();
    }
    $type = $this->profile->byType($target->type);
    $holder = array_find($collections, fn (Collection $collection): bool => $collection->type === $target->type);
    $shown = (new LocalisedText($type))->shown($target, $in);
    $title = ($holder?->name ?? $target->typeName()) . ' · ' . $target->slug->value;
    $description = $target->typeName() === 'MediaObject' ? trim((string) $shown->props->string('description')) : '';
    $noAlt = $description === '' && $target->typeName() === 'MediaObject'
      && str_starts_with((string) $target->props->string('encoding_format'), 'image/');

    return new Chip($this->label->of($shown, $type), $title, SidebarEntry::glyphOf($target->type), noAlt: $noAlt,
      href: $holder === null ? null : self::address($holder, $target), caption: $description,
      preview: self::preview($target));
  }

  /* The record screen of a record, in the locale it is written in. */
  public static function address(Collection $collection, Entity $entity, string $query = ''): string
  {
    return '/c/' . $collection->slug->value . '/' . $entity->slug->value . '?'
      . http_build_query(['in' => $entity->locale->tag]) . ($query === '' ? '' : '&' . $query);
  }

  /* The record screen of a record where the reader may open it, as its chip would link it.
     @param list<Collection> $collections */
  public function opened(Account $account, Entity $record, array $collections): ?string
  {
    $holder = array_find($collections, fn (Collection $collection): bool => $collection->type === $record->type);

    return $holder !== null && $this->readable($account, $record) ? self::address($holder, $record) : null;
  }

  /* The picture a screen shows for an asset, and the only one it loads: a raster
     image's preview, an SVG itself, which runs nothing in an `<img>`, and nothing
     for a file elsewhere, which `img-src 'self'` would refuse anyway. */
  public static function preview(Entity $asset): ?string
  {
    $address = (string) $asset->props->string('content_url');
    $mime = (string) $asset->props->string('encoding_format');

    return match (true) {
      !str_starts_with($mime, 'image/') || !str_starts_with($address, Intake::ADDRESS) => null,
      $mime === 'image/svg+xml' => $address,
      default => $address . '/thumb',
    };
  }

  /* The fields of a collection this account reads: all it carries inside the
     read scope, and outside it what the public reads.
     @return list<CollectionField> */
  public function fields(Account $account, Collection $collection): array
  {
    return $this->authorizer->readsAsPublic($account, $collection->type) ? $this->filter->fields($collection)
      : $collection->fields;
  }

  /* What this account reads of each collection, as the query narrowing to it:
     all of a type in the read scope, and outside it what the public reads.
     @param list<Collection> $collections
     @return array<string, Query> by type */
  public function readings(Account $account, array $collections): array
  {
    $readings = [];
    foreach ($collections as $collection) {
      $keys = array_map(fn (CollectionField $field): string => $field->key, $collection->fields);
      $readings += $this->authorizer->readsAsPublic($account, $collection->type)
        ? $this->filter->readings([$collection])
        : [$collection->type => new Query($collection->type, searchIn: $keys)];
    }

    return $readings;
  }

  /* Whoever writes a collection and reads it as staff takes a record back out of its trash. */
  public function opensTrash(Account $account, Collection $collection): bool
  {
    return !$this->authorizer->readsAsPublic($account, $collection->type)
      && $this->authorizer->writes($account, $collection);
  }

  public function readable(Account $account, Entity $target): bool
  {
    return !$this->authorizer->readsAsPublic($account, $target->type)
      || ($this->filter->passesType($target->type)
        && $target->status->isPublicAt($this->clock->now(), $target->publishFrom, $target->publishTo));
  }
}
