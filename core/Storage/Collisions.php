<?php

declare(strict_types=1);

namespace Storage;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\SlugTaken;
use Domain\Exceptions\ValueTaken;
use Domain\Locale;
use Domain\Relation;
use Domain\Slug;
use Domain\TranslationGroup;
use Domain\Violation;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Support\Json;

/* What a save may not collide with, asked inside `EntityRepository::save`'s
   transaction: uniqueness needs the database, so the profile states the rule and
   the chokepoint enforces it, each collision a named refusal. A `!` scoped by an
   edge is checked against the edges the same save writes, and a record lacking
   the edge is scoped with the others lacking it: the roots' term codes are held
   once among them. A value is held as spelt, `RE-1` and `re-1` being two. A
   record in the trash keeps its slug and its values, so restoring never
   collides, and the refusal says so. A repeated order is answered by its live
   holder only: taking one out of the trash is the house's act, not a channel's. */
final readonly class Collisions
{
  public const IN_LOCALE = '(locale = :locale or type in (select value from json_each(:across)))';

  public function __construct(
    private \PDO $db,
    private Profile $profile,
    private SchemaOrg $schema,
    private Json $json = new Json(),
  ) {}

  public function requireFreeSlug(Entity $entity): void
  {
    $statement = $this->db->prepare('select 1 from entity where type = :type'
      . ' and slug = :slug and id <> :id and ' . self::IN_LOCALE);
    $statement->execute(['type' => $entity->type,
      'slug' => $entity->slug->value, 'id' => $entity->id->value,
      ...$this->localeScope($entity->type, $entity->locale)]);
    if ($statement->fetchColumn() !== false) {
      throw SlugTaken::named($entity->slug->value, $entity->locale->tag);
    }
  }

  /* The first spelling of a new record's slug that nothing of its type in its
     locales holds — `till`, then `till-2` — so no caller is asked to pick one
     (`Domain\Slug`). Per type, since the type is part of the address: a site, a
     brand and a software may all be `skemik`. */
  public function freeSlug(Entity $entity): Slug
  {
    $statement = $this->db->prepare('select slug from entity where type = :type'
      . " and id <> :id and (slug = :slug or substr(slug, 1, length(:slug) + 1) = :slug || '-') and "
      . self::IN_LOCALE);
    $statement->execute(['type' => $entity->type,
      'slug' => $entity->slug->value, 'id' => $entity->id->value,
      ...$this->localeScope($entity->type, $entity->locale)]);
    $taken = array_flip($statement->fetchAll(\PDO::FETCH_COLUMN));
    $free = $entity->slug;
    for ($number = 2; isset($taken[$free->value]); $number++) {
      $free = $entity->slug->numbered($number);
    }

    return $free;
  }

  /** @param list<Relation> $edges */
  public function requireUnique(Entity $entity, array $edges): void
  {
    $type = $this->profile->byType($entity->type);
    foreach ($type->fields() as $field) {
      $holder = $this->heldBy($entity, $field, $edges);
      if ($holder !== null) {
        $scope = $type->field((string) $field->unique?->edge);
        $where = 'this ' . ($scope === null ? 'house' : mb_strtolower($scope->label));
        $trash = $this->inTrash($holder) ? ', in the trash' : '';
        $reason = sprintf('another %s in %s holds it%s', $type->name, $where, $trash);
        throw new ValueTaken(new Violation($field->key, $reason));
      }
    }
  }

  /* The live record already holding one of this record's `!` values: what a
     repeated order is answered with, where anything else is refused.
     @param list<Relation> $edges */
  public function holder(Entity $entity, array $edges): ?EntityId
  {
    foreach ($this->profile->byType($entity->type)->fields() as $field) {
      $holder = $this->heldBy($entity, $field, $edges);
      if ($holder !== null && !$this->inTrash($holder)) {
        return new EntityId($holder);
      }
    }

    return null;
  }

  /* The group this save leaves must be one `Domain\TranslationGroup` can load,
     or the write is accepted and every read of the group fails: one member per
     locale, and a root of the same forking type that is no member itself. A
     member in the trash holds its locale as a `!` value is held there, and the
     refusal says so, since restoring or purging it is the way out. */
  public function requireFreeTranslation(Entity $entity): void
  {
    if ($entity->translationOf !== null) {
      $this->requireRoot($entity, $entity->translationOf);
    }
    $holder = $this->translationHolder($entity);
    if ($holder !== null) {
      $trash = $this->inTrash($holder->value) ? ', in the trash' : '';
      $reason = sprintf('another %s in this group holds it%s', $entity->typeName(), $trash);
      throw new ValueTaken(new Violation('locale', $reason));
    }
  }

  /* The member of the record's group written in its locale, trashed or not,
     and null where the locale is free; the record itself never counts. */
  public function translationHolder(Entity $entity): ?EntityId
  {
    $statement = $this->db->prepare('select e.id from (select :root as id union all select id from entity'
      . ' where translation_of = :root) g cross join entity e on e.id = g.id'
      . ' where e.id <> :id and e.locale = :locale');
    $statement->execute(['id' => $entity->id->value,
      'locale' => $entity->locale->tag, 'root' => TranslationGroup::rootOf($entity)->value]);
    $holder = $statement->fetchColumn();

    return $holder === false ? null : new EntityId((string) $holder);
  }

  /* A type that does not fork answers on every locale route, so it meets
     every row of such a type whatever that row's locale. Bound as a list rather
     than a flag, since a bound flag is text and never equals `1`.
     @return array{locale: string, across: string} */
  public function localeScope(string $type, Locale $locale): array
  {
    $across = [];
    foreach ($this->profile->types() as $candidate) {
      if ($candidate->locale->answersOnEveryLocale()) {
        $across[] = $candidate->url();
      }
    }
    $answersEverywhere = in_array($this->profile->byType($type)->url(), $across, true);

    return ['locale' => $locale->tag, 'across' => $this->json->encode($answersEverywhere ? $across : [])];
  }

  private function requireRoot(Entity $entity, EntityId $root): void
  {
    if (!$this->profile->byType($entity->type)->locale->forks()) {
      throw MalformedValue::named('type that forks', $entity->typeName());
    }
    $statement = $this->db->prepare('select type = :type and translation_of is null and id <> :id'
      . ' and not exists (select 1 from entity m where m.translation_of = :id)'
      . ' from entity where id = :root');
    $statement->execute(['type' => $entity->type,
      'id' => $entity->id->value, 'root' => $root->value]);
    $isRoot = $statement->fetchColumn();
    if ($isRoot === false) {
      throw EntityNotFound::withId($root->value);
    }
    if ((int) $isRoot !== 1) {
      throw MalformedValue::named('root of a group of ' . $entity->typeName(), $root->value);
    }
  }

  /** @param list<Relation> $edges */
  private function heldBy(Entity $entity, FieldDefinition $field, array $edges): ?string
  {
    $value = $entity->props->document()[$field->key] ?? null;
    $edgeKey = $field->unique?->edge;
    $scope = $edgeKey === null ? [] : $this->scope($edgeKey, $edges);
    if ($field->unique === null || !is_scalar($value)) {
      return null;
    }
    $statement = $this->db->prepare('select e.id from entity e where e.type = :type'
      . " and e.id <> :id and json_extract(e.props, :path) = json_extract(:value, '$')"
      . ' and (:predicate is null'
      . ' or (:target is null and not exists (select 1 from relation r'
      . ' where r.subject_id = e.id and r.predicate = :predicate))'
      . ' or exists (select 1 from relation r where r.subject_id = e.id'
      . ' and r.predicate = :predicate and r.object_id = :target)) limit 1');
    $statement->execute(['type' => $entity->type,
      'id' => $entity->id->value, 'path' => '$.' . $field->key, 'value' => $this->json->encode($value),
      'predicate' => $scope['predicate'] ?? null, 'target' => $scope['target'] ?? null]);
    $holder = $statement->fetchColumn();

    return $holder === false ? null : (string) $holder;
  }

  private function inTrash(string $id): bool
  {
    $statement = $this->db->prepare('select deleted_at is not null from entity'
      . ' where id = :id');
    $statement->execute(['id' => $id]);

    return (int) $statement->fetchColumn() === 1;
  }

  /**
   * @param list<Relation> $edges
   * @return array{predicate: string, target: ?string} the target null where the record names no such edge
   */
  private function scope(string $edgeKey, array $edges): array
  {
    $predicate = (string) $this->schema->propertyName($edgeKey);
    foreach ($edges as $edge) {
      if ($edge->predicate === $predicate) {
        return ['predicate' => $predicate, 'target' => $edge->object->value];
      }
    }

    return ['predicate' => $predicate, 'target' => null];
  }
}
