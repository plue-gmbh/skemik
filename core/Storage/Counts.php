<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Profile\FieldDefinition;
use Profile\SchemaOrg;
use Support\Clock;
use Support\Json;

/* The sidebar's numbers and serving marks in one grouped query per request,
   rather than a count per entry, which would scan `entity` once per collection
   to draw a list. Per type, because a row belongs to its type and every
   collection of that type shows the same rows. The query bar's groups are asked
   here too, offering only what rows hold, and the fields a view beside the table
   shows, leaving out what nobody fills. */
final readonly class Counts
{
  private const OF_TYPE = 'e.type = :type and e.deleted_at is null and (:outside is null or ' . Query::PUBLIC . ')';

  public function __construct(private \PDO $db, private SchemaOrg $schema, private Clock $clock,
    private Json $json = new Json()) {}

  /** @return array<string, TypeCount> by type URL; a type with no rows is absent */
  public function byType(): array
  {
    $statement = $this->db->prepare('select e.type, count(*) as rows, sum(' . Query::PUBLIC . ') as public'
      . ' from entity e where e.deleted_at is null group by e.type');
    $statement->execute(['now' => $this->clock->now()->format(Clock::STORED)]);
    $counts = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $type = (string) $row['type'];
      $counts[$type] = new TypeCount($type, (int) $row['rows'], (int) $row['public']);
    }

    return $counts;
  }

  /** Which of a type's fields some record outside the trash holds; each `exists` stops at the first it
      finds. A derived field is asked for no colleague: whether its value is null turns on the record and
      its gate, never on who reads the targets.
      @param list<FieldDefinition> $fields
      @return list<string> their keys */
  public function filled(string $type, array $fields): array
  {
    if ($fields === []) {
      return [];
    }
    $fields = array_values($fields);
    $asked = ['type' => $type];
    $clauses = ['0'];
    foreach ($fields as $index => $field) {
      $asked["k$index"] = $this->address($field);
      $clauses[] = 'exists (select 1 from entity e' . self::holding($field, ":k$index")
        . ' e.type = :type and e.deleted_at is null)';
    }
    $statement = $this->db->prepare('select ' . implode(', ', $clauses));
    $statement->execute($asked);
    $found = array_filter(array_slice((array) $statement->fetch(\PDO::FETCH_NUM), 1));

    return array_map(fn (int $index): string => $fields[$index]->key, array_keys($found));
  }

  /* A derived field first, since a price range is declared as the edge it reads. */
  private function address(FieldDefinition $field): string
  {
    return match (true) {
      Computed::holds($field) => Computed::spec($field, $this->schema, null),
      $field->cardinality->isEdge() => (string) $this->schema->propertyName($field->key),
      default => '$.' . $field->key,
    };
  }

  /* What narrows a row to one holding the field, up to where the type's condition follows. */
  private static function holding(FieldDefinition $field, string $key): string
  {
    return match (true) {
      Computed::holds($field) => ' where ' . Computed::stated($field, $key) . ' and',
      $field->cardinality->isEdge() => " join relation r on r.subject_id = e.id and r.predicate = $key where",
      default => " where nullif(nullif(json_extract(e.props, $key), ''), '[]') is not null and",
    };
  }

  /** What rows of the type hold, so the query bar offers nothing else: `locale` and
      `status` from the rows' own columns, the status as a filter reads it, and each
      field key asked from the values, every token of a repeated one. Locale and
      status come from one scan, since a second costs as much again; outside the
      reader's scope only what a public row holds.
      @param list<string> $keys
      @return array<string, list<string>> by key; a key no row holds is absent */
  public function held(string $type, array $keys, bool $publicOnly): array
  {
    $bindings = $this->bindings($type, $publicOnly);
    $statement = $this->db->prepare('select distinct e.locale, ' . Query::STATUS . ' as status from entity e'
      . ' where ' . self::OF_TYPE . ' order by e.locale');
    $statement->execute($bindings);
    $held = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $held['locale'][(string) $row['locale']] = true;
      if ($row['status'] !== null) {
        $held['status'][(string) $row['status']] = true;
      }
    }

    $columned = array_values(array_filter($keys, fn (string $key): bool => Columns::holds($type, $key)));

    return [...array_map(array_keys(...), $held), ...$this->columned($columned, $bindings),
      ...$this->values(array_values(array_diff($keys, $columned)), $bindings)];
  }

  /** @param list<string> $keys
      @param array<string, ?string> $bindings
      @return array<string, list<string>> */
  private function columned(array $keys, array $bindings): array
  {
    $tokens = array_map(fn (string $key): array => $this->column($key, $bindings), array_combine($keys, $keys));

    return array_filter($tokens);
  }

  /** A key with a column (`Columns`) read down its index one value at a time, each the least above the last:
      SQLite takes no index on a virtual column as covering, so a `distinct` read every row of the type, some
      200 ms of 90 000. Outside the scope the public gate reads rows until one of a value passes.
      @param array<string, ?string> $bindings
      @return list<string> */
  private function column(string $key, array $bindings): array
  {
    $column = 'e.' . Columns::of($key);
    $inside = $bindings['outside'] === null;
    $of = $inside ? 'e.type = :type and e.deleted_at is null' : self::OF_TYPE;
    $statement = $this->db->prepare("with recursive held(value) as (select min($column) from entity e where $of"
      . " and $column is not null union all select (select min($column) from entity e where $of"
      . " and $column > held.value) from held where held.value is not null)"
      . ' select value from held where value is not null');
    $statement->execute($inside ? ['type' => $bindings['type']] : $bindings);

    return array_map(strval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
  }

  /** Every token of a key held in `props` alone, a repeated one's among them.
      @param list<string> $keys
      @param array<string, ?string> $bindings
      @return array<string, list<string>> */
  private function values(array $keys, array $bindings): array
  {
    if ($keys === []) {
      return [];
    }
    $statement = $this->db->prepare("select distinct k.value as key, v.value from entity e, json_each(:keys) k,"
      . " json_each(e.props, '$.' || k.value) v where " . self::OF_TYPE . " and v.type = 'text'");
    $statement->execute([...$bindings, 'keys' => $this->json->encode($keys)]);
    $values = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $values[(string) $row['key']][] = (string) $row['value'];
    }

    return $values;
  }

  /** The tokens of a key rows of the type hold most often, at most `$few` of them, a repeated
      one's each, in the reader's scope as `held` reads it.
      @return list<string> the most held first */
  public function most(string $type, string $key, bool $publicOnly, int $few): array
  {
    $columned = Columns::holds($type, $key);
    $from = $columned ? 'e.' . Columns::of($key) . ' as value from entity e where'
      : "v.value from entity e, json_each(e.props, :path) v where v.type = 'text' and";
    $statement = $this->db->prepare("select value from (select $from " . self::OF_TYPE . ')'
      . ' where value is not null group by value order by count(*) desc, value limit cast(:few as integer)');
    $path = $columned ? [] : ['path' => '$.' . $key];
    $statement->execute([...$this->bindings($type, $publicOnly), ...$path, 'few' => (string) $few]);

    return array_map(strval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
  }

  /** The records rows of the type point at through a predicate, each with the rest of its
      translation group, which a filter naming one finds as well (`Feed\Targets`); outside
      the reader's scope only what a public row points at. Where a bound is given, a
      predicate pointing at more works than that is absent, a work counted once for all its
      translations as the query bar offers it. Asked a predicate at a time, since `distinct`
      under a `limit` stops one past the bound, where one query over all of them ranks every
      customer of every order before the bound drops the group. The targets are walked down the
      edges' index and each asked whether a row of the type points at it, so the cost follows
      the predicate's targets and not the rows: 15 terms of 90 000 businesses took 223 ms the
      other way round, some 4 this way, and 6 000 customers no person points at some 80. The edge
      leads by `cross join`, since the public gate otherwise has SQLite start from every row.
      @param list<string> $predicates
      @return array<string, list<EntityId>> by predicate; one no row points with is absent */
  public function targets(string $type, array $predicates, bool $publicOnly, ?int $few): array
  {
    $statement = $this->db->prepare('with recursive o(id) as (select min(object_id) from relation'
      . ' where predicate = :predicate union all select (select min(object_id) from relation'
      . ' where predicate = :predicate and object_id > o.id) from o where o.id is not null)'
      . ' select distinct coalesce(t.translation_of, t.id) from o join entity t on t.id = o.id'
      . ' and t.deleted_at is null where exists (select 1 from relation r cross join entity e on e.id = r.subject_id'
      . ' where r.object_id = o.id and r.predicate = :predicate and ' . self::OF_TYPE . ')'
      . ' limit cast(:at_most as integer)');
    $bindings = [...$this->bindings($type, $publicOnly), 'at_most' => (string) ($few === null ? -1 : $few + 1)];
    $targets = [];
    foreach ($predicates as $predicate) {
      $statement->execute([...$bindings, 'predicate' => $predicate]);
      $works = $statement->fetchAll(\PDO::FETCH_COLUMN);
      if ($works !== [] && ($few === null || count($works) <= $few)) {
        $targets[$predicate] = $this->members($works);
      }
    }

    return $targets;
  }

  /** @param list<string> $works the groups' roots, a record that forks nothing its own
      @return list<EntityId> */
  private function members(array $works): array
  {
    $statement = $this->db->prepare('select id from entity where deleted_at is null and'
      . ' (id in (select value from json_each(:works)) or translation_of in (select value from json_each(:works)))');
    $statement->execute(['works' => $this->json->encode($works)]);

    return array_map(fn (string $id): EntityId => new EntityId($id), $statement->fetchAll(\PDO::FETCH_COLUMN));
  }

  /** `outside` is null for a reader inside the type's scope, which lifts the public gate.
      @return array<string, ?string> */
  private function bindings(string $type, bool $publicOnly): array
  {
    return ['type' => $type, 'now' => $this->clock->now()->format(Clock::STORED),
      'outside' => $publicOnly ? '1' : null];
  }
}
