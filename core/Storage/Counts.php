<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Support\Clock;
use Support\Json;

/* The sidebar's numbers and serving marks in one grouped query per request,
   rather than a count per entry, which would scan `entity` once per collection
   to draw a list. Per type, because a row belongs to its type and every
   collection of that type shows the same rows. The query bar's groups are asked
   here too, offering only what rows hold. */
final readonly class Counts
{
  private const OF_TYPE = 'e.type = :type and e.deleted_at is null and (:outside is null or ' . Query::PUBLIC . ')';

  public function __construct(private \PDO $db, private Clock $clock, private Json $json = new Json()) {}

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

    return [...array_map(array_keys(...), $held), ...$this->values($keys, $bindings)];
  }

  /** @param list<string> $keys
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

  /** The records rows of the type point at through a predicate, each with the rest of its
      translation group, which a filter naming one finds as well (`Feed\Targets`); outside
      the reader's scope only what a public row points at. Where a bound is given, a
      predicate pointing at more works than that is absent, a work counted once for all its
      translations as the query bar offers it. Asked a predicate at a time, since `distinct`
      under a `limit` stops one past the bound, where one query over all of them ranks every
      customer of every order before the bound drops the group.
      @param list<string> $predicates
      @return array<string, list<EntityId>> by predicate; one no row points with is absent */
  public function targets(string $type, array $predicates, bool $publicOnly, ?int $few): array
  {
    $statement = $this->db->prepare('select distinct coalesce(t.translation_of, t.id) from entity e'
      . ' join relation r on r.subject_id = e.id and r.predicate = :predicate join entity t on t.id = r.object_id'
      . ' and t.deleted_at is null where ' . self::OF_TYPE . ' limit cast(:at_most as integer)');
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
