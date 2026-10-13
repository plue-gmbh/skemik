<?php

declare(strict_types=1);

namespace Storage;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\StaleWrite;
use Domain\Locale;
use Domain\Props;
use Domain\Relation;
use Domain\Slug;
use Domain\Status;
use Domain\TranslationGroup;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Support\Clock;
use Support\Json;

/* The chokepoint, accepting no SQL fragment from a caller. A record is its row
   and its outbound edges, saved in one `begin immediate`, so nothing slips
   between the version, the slug and `Collisions` read there and the write: two
   new records of one name arriving at once are `till` and `till-2`. `sort_keys`
   and `words` are written with `props`. A save changing nothing writes nothing,
   so a channel sending the same stock every minute moves neither the version a
   colleague's open record holds nor the log. The trash and the purge are
   `Trash`'s. */
final readonly class EntityRepository
{
  public function __construct(
    private \PDO $db,
    private Profile $profile,
    private SchemaOrg $schema,
    private Collisions $collisions,
    private ActivityLog $log,
    private RelationRepository $relations,
    private Search $search,
    private Clock $clock,
    private SortKeys $sortKeys = new SortKeys(),
    private Transaction $transaction = new Transaction(),
    private Json $json = new Json(),
  ) {}

  public function find(EntityId $id): Entity
  {
    $statement = $this->db->prepare('select * from entity where id = :id');
    $statement->execute(['id' => $id->value]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? throw EntityNotFound::withId($id->value) : $this->entity($row);
  }

  /* The type is part of the address: a labelled thing answers on every
     locale route, so without it a `Person` and an `Article` may share one. */
  public function bySlug(string $type, Locale $locale, Slug $slug): ?Entity
  {
    $url = $this->profile->byType($type)->url();
    $statement = $this->db->prepare('select * from entity where type = :type'
      . ' and slug = :slug and deleted_at is null and ' . Collisions::IN_LOCALE);
    $statement->execute(['type' => $url, 'slug' => $slug->value,
      ...$this->collisions->localeScope($url, $locale)]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $this->entity($row);
  }

  /* The translation group a work belongs to, for `Feed\JsonLd`'s switcher edges:
     one lookup by the root, since a member names the root and never another
     member. Trashed members are left out, as everywhere else. */
  public function group(Entity $member): TranslationGroup
  {
    $statement = $this->db->prepare('select * from entity'
      . ' where (id = :root or translation_of = :root) and deleted_at is null');
    $statement->execute(['root' => TranslationGroup::rootOf($member)->value]);
    $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

    return new TranslationGroup($this->profile->byType($member->type),
      $rows === [] ? [$member] : array_map($this->entity(...), $rows));
  }

  /* The works of a type in one locale that are in no group, a member in the
     trash counting as one, as `Collisions` counts it: what may join another's.
     @return list<Entity> */
  public function ungrouped(string $type, Locale $locale): array
  {
    $statement = $this->db->prepare('select * from entity e where type = :type and locale = :locale'
      . ' and translation_of is null and deleted_at is null'
      . ' and not exists (select 1 from entity m where m.translation_of = e.id) order by slug');
    $statement->execute(['type' => $this->profile->byType($type)->url(), 'locale' => $locale->tag]);

    return array_map($this->entity(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  /* The page is chosen by id and its rows read after: sorting whole rows to keep
     fifty cost five times as much at 50 000. @return list<Entity> trashed left out */
  public function list(Query $query): array
  {
    $type = $this->profile->byType($query->type);
    $page = $this->db->prepare('select e.id from ' . $query->from() . ' where ' . $query->where($type)
      . $query->page($type));
    $page->execute([...$this->bindings($query, $type), ...$query->sortBindings($type, $this->schema)]);
    $ids = $page->fetchAll(\PDO::FETCH_COLUMN);
    $statement = $this->db->prepare('select * from entity'
      . ' where id in (select value from json_each(:ids))');
    $statement->execute(['ids' => $this->json->encode($ids)]);
    $rows = array_column($statement->fetchAll(\PDO::FETCH_ASSOC), null, 'id');

    return array_map(fn (string $id): Entity => $this->entity($rows[$id]), $ids);
  }

  public function count(Query $query): int
  {
    $type = $this->profile->byType($query->type);
    $statement = $this->db->prepare('select count(*) from entity e where ' . $query->where($type));
    $statement->execute($this->bindings($query, $type));

    return (int) $statement->fetchColumn();
  }

  /** @param list<Relation> $edges every outbound edge, as it will stand */
  public function save(Entity $entity, array $edges, ?int $expectedVersion = null): Entity
  {
    $type = $this->profile->byType($entity->type);
    $this->transaction->run($this->db, function () use ($entity, $type, $edges, $expectedVersion): void {
      $stored = $this->requireSaveable($entity, $expectedVersion);
      if ($stored !== null && $entity->holdsAs($stored) && $this->relations->holds($entity->id, $edges)) {
        return;
      }
      $entity = $stored === null ? $entity->withSlug($this->collisions->freeSlug($entity)) : $entity;
      $this->collisions->requireFreeSlug($entity);
      $this->collisions->requireFreeTranslation($entity);
      $this->collisions->requireUnique($entity, $edges);
      $stored === null ? $this->insert($entity, $type) : $this->update($entity, $type);
      $this->relations->replace($entity->id, $edges);
      $this->log->recordSave($entity, $stored, $type);
    });

    return $this->find($entity->id);
  }

  /* @return ?Entity the record as stored, null when it is new */
  private function requireSaveable(Entity $entity, ?int $expectedVersion): ?Entity
  {
    try {
      $stored = $this->find($entity->id);
    } catch (EntityNotFound $notFound) {
      return $expectedVersion === null ? null : throw $notFound;
    }
    if ($stored->type !== $entity->type) {
      throw MalformedValue::named('type of ' . $entity->id->value, $entity->type);
    }

    return $expectedVersion === null || $stored->version === $expectedVersion ? $stored
      : throw StaleWrite::expected($expectedVersion);
  }

  private function insert(Entity $entity, TypeDefinition $type): void
  {
    $this->db->prepare('insert into entity (id, type, props, sort_keys, words, status,'
      . ' locale, translation_of, slug, publish_from, publish_to, created_at, updated_at)'
      . ' values (:id, :type, :props, :sort_keys, :words, :status, :locale,'
      . ' :translation_of, :slug, :publish_from, :publish_to, :now, :now)')
      ->execute([...$this->columns($entity, $type), 'type' => $entity->type]);
  }

  private function update(Entity $entity, TypeDefinition $type): void
  {
    $this->db->prepare('update entity set props = :props, sort_keys = :sort_keys,'
      . ' words = :words, status = :status, locale = :locale, translation_of = :translation_of,'
      . ' slug = :slug, publish_from = :publish_from, publish_to = :publish_to, version = version + 1,'
      . ' updated_at = :now where id = :id')
      ->execute([...$this->columns($entity, $type)]);
  }

  /** @return array<string, string|null> */
  private function bindings(Query $query, TypeDefinition $type): array
  {
    return [...$query->bindings($type, $this->schema, $this->sortKeys), 'locale' => null, 'across' => '[]',
      ...($query->locale === null ? [] : $this->collisions->localeScope($type->name, $query->locale)),
      'now' => $this->now(),
      'match' => $query->search === '' ? null : $this->search->match($query->search, $query->searchIn)];
  }

  /** @return array<string, string|null> */
  private function columns(Entity $entity, TypeDefinition $type): array
  {
    return [
      'id' => $entity->id->value,
      'props' => $this->json->encode((object) $entity->props->document()),
      'sort_keys' => $this->sortKeys->of($type, $entity->props),
      'words' => $this->search->words($type, $entity->props),
      'status' => $entity->status->value,
      'locale' => $entity->locale->tag,
      'translation_of' => $entity->translationOf?->value,
      'slug' => $entity->slug->value,
      'publish_from' => $entity->publishFrom,
      'publish_to' => $entity->publishTo,
      'now' => $this->now(),
    ];
  }

  /** @param array<string, string|int|null> $row */
  private function entity(array $row): Entity
  {
    $optional = fn (string $column): ?string => $row[$column] === null ? null : (string) $row[$column];

    return new Entity(
      new EntityId((string) $row['id']),
      (string) $row['type'],
      new Locale((string) $row['locale']),
      new Slug((string) $row['slug']),
      new Props($this->json->decode((string) $row['props'])),
      (string) $row['created_at'],
      (string) $row['updated_at'],
      Status::from((string) $row['status']),
      $row['translation_of'] === null ? null : new EntityId((string) $row['translation_of']),
      (int) $row['version'],
      $optional('publish_from'),
      $optional('publish_to'),
      $optional('deleted_at'),
    );
  }

  private function now(): string
  {
    return $this->clock->now()->format(Clock::STORED);
  }
}
