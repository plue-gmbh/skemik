<?php

declare(strict_types=1);

namespace Storage;

use Support\Clock;

/* When the house last changed what any reading of it may show, one date for the
   whole house and never older than a document, at the price of moving for more
   than changed: the newest line of the log, since every write is logged and a
   trashing or a purge leaves no row to date; the newest window edge passed, any
   status, since a record goes live or comes down without a write; the newest
   offer lapsed, which leaves its item's price the same way (`Feed\Derivations`);
   and the stamp a release or a restore writes, since either can render the same
   rows anew. A data folder copied back by hand moves it back, so a reader
   compares it for inequality only. */
final readonly class Modified
{
  private const OFFER = 'https://schema.org/Offer';

  public function __construct(private \PDO $db, private Clock $clock, private string $stamp) {}

  public function at(): string
  {
    $now = $this->clock->now()->format(Clock::STORED);
    $logged = $this->db->query('select max(start_time) from activity')->fetchColumn();
    $window = $this->db->prepare('select max(case when publish_from <= :now then publish_from end),'
      . ' max(case when publish_to <= :now then publish_to end) from entity');
    $window->execute(['now' => $now]);

    return max([gmdate(Clock::STORED, (int) filemtime($this->stamp)), (string) $logged,
      ...array_map('strval', (array) $window->fetch(\PDO::FETCH_NUM)), $this->lapsed($now)]);
  }

  /* A valid-through date is a day, a month or a year, and lapses as the house's next one begins. */
  private function lapsed(string $now): string
  {
    $statement = $this->db->prepare("select distinct json_extract(props, '$.valid_through') from entity"
      . " where type = :type and json_extract(props, '$.valid_through') < :today");
    $statement->execute(['type' => self::OFFER, 'today' => substr($this->clock->render($now), 0, 10)]);
    $lapses = array_map(fn (string $through): string => $this->clock->dayStart(match (strlen($through)) {
      4 => sprintf('%04d-01-01', (int) $through + 1),
      7 => (new \DateTimeImmutable($through . '-01'))->modify('+1 month')->format('Y-m-d'),
      default => (new \DateTimeImmutable($through))->modify('+1 day')->format('Y-m-d'),
    }), $statement->fetchAll(\PDO::FETCH_COLUMN));

    return max(['', ...array_filter($lapses, fn (string $lapse): bool => $lapse <= $now)]);
  }
}
