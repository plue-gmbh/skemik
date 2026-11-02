<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Support\Clock;
use Support\Json;

/* The sidebar's numbers and serving marks in one grouped query per request,
   rather than a count per entry, which would scan `entity` once per collection
   to draw a list. Per type, because a row belongs to its type and every
   collection of that type shows the same rows. The query bar's groups over the
   edges are asked the same way, once per type and not once per field. */
final readonly class Counts
{
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

  /** The query bar's Language group: only locales a row of the type is written in,
      and outside the reader's scope only those a public row is.
      @return list<string> */
  public function locales(string $type, bool $publicOnly): array
  {
    $statement = $this->db->prepare('select distinct e.locale from entity e where e.type = :type'
      . ' and e.deleted_at is null and (:now is null or ' . Query::PUBLIC . ')'
      . ' order by e.locale');
    $statement->execute(['type' => $type,
      'now' => $publicOnly ? $this->clock->now()->format(Clock::STORED) : null]);

    return $statement->fetchAll(\PDO::FETCH_COLUMN);
  }

  /** The records rows of the type point at through each predicate, each once, in
      the order of their slugs, and where a bound is given no more than that many
      a predicate, so a group past it is seen to be and offered whole or not at
      all; outside the reader's scope only what a public row points at.
      @param list<string> $predicates
      @return array<string, list<EntityId>> by predicate; one no row points with is absent */
  public function targets(string $type, array $predicates, bool $publicOnly, ?int $atMost): array
  {
    $statement = $this->db->prepare('select predicate, object_id from (select predicate, object_id,'
      . ' row_number() over (partition by predicate order by slug) as n from (select distinct r.predicate,'
      . ' r.object_id, t.slug from entity e join relation r on r.subject_id = e.id join entity t on t.id = r.object_id'
      . ' where e.type = :type and e.deleted_at is null and (:now is null or ' . Query::PUBLIC . ')'
      . ' and t.deleted_at is null and r.predicate in (select value from json_each(:predicates))))'
      . ' where :at_most is null or n <= cast(:at_most as integer) order by predicate, n');
    $statement->execute(['type' => $type, 'now' => $publicOnly ? $this->clock->now()->format(Clock::STORED) : null,
      'predicates' => $this->json->encode($predicates), 'at_most' => $atMost]);
    $targets = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $targets[(string) $row['predicate']][] = new EntityId((string) $row['object_id']);
    }

    return $targets;
  }
}
