<?php

declare(strict_types=1);

namespace Update;

/* Which snapshots stay: every one of the newest day, the newest of each of the
   six days before it that have one, and before them the newest of each of the
   four newest weeks, about thirty days. A day's newest holds what the day did,
   and a press or a release taking more of them would otherwise cost the install a
   copy of its database each for a week. A rung width rather than a limit, since
   the snapshots stopped being somebody's decision; narrower rungs are what the
   daily run shrinks to before it skips a day. Days and weeks as the install lives
   them. */
final readonly class Ladder
{
  public function __construct(public int $days = 7, public int $weeks = 4) {}

  /** @param list<Snapshot> $snapshots newest first
      @return list<Snapshot> the ones the ladder has passed */
  public function passed(array $snapshots, \DateTimeZone $zone): array
  {
    $days = [];
    $weeks = [];
    $passed = [];
    $newest = null;
    foreach ($snapshots as $snapshot) {
      $local = $snapshot->at->setTimezone($zone);
      $day = $local->format('Y-m-d');
      $week = $local->format('o-\WW');
      $newest ??= $day;
      if ($day === $newest || (!isset($days[$day]) && count($days) < $this->days)) {
        $days[$day] = true;
      } elseif (!isset($days[$day]) && !isset($weeks[$week]) && count($weeks) < $this->weeks) {
        $weeks[$week] = true;
      } else {
        $passed[] = $snapshot;
      }
    }

    return $passed;
  }
}
