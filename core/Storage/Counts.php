<?php

declare(strict_types=1);

namespace Storage;

use Support\Clock;

/* The sidebar's numbers and serving marks in one grouped query per request,
   rather than a count per entry, which would scan `entity` once per collection
   to draw a list. Per type, because a row belongs to its type and every
   collection of that type shows the same rows. */
final readonly class Counts
{
  public function __construct(private \PDO $db, private Clock $clock) {}

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

  /* The Languages card's numbers: the records written in each locale, as their
     own row or as words under `l10n`, one record counted once per locale.
     @return array<string, int> by tag; a locale nothing is written in is absent */
  public function byLocale(): array
  {
    $statement = $this->db->prepare('select tag, count(*) from (select id, locale as tag from entity'
      . ' where deleted_at is null'
      . " union select e.id, l.key from entity e, json_each(e.props, '$.l10n') l"
      . ' where e.deleted_at is null) group by tag');
    $statement->execute();

    return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_KEY_PAIR));
  }

  /* The query bar's Language group: only locales a row of the type is written in,
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
}
