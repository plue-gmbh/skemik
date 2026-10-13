<?php

declare(strict_types=1);

namespace Update;

use Http\Response;
use Support\Clock;

/* The snapshot nobody asks for: one a day, on the first request of the day as
   the house lives it, after the answer has left and under a lock, there being
   no daemon and no cron to set up. Where it will not fit the window shrinks
   before the day is lost: the weekly rungs go first, then the dailies to three,
   and the three newest are never deleted to make room. What it did is the
   report beside the snapshots, written before the attempt as well, so a disk
   that takes no report is asked once a request and not for a copy; the backups
   screen and Needs a look read it, which makes a daily failing for three weeks a
   line on a screen and not an absence. Nothing where `config.php` switches the
   daily ones off. */
final readonly class Daily
{
  public function __construct(private Backup $backup, private Clock $clock) {}

  /* An answer that has work of its own, or streams a file, leaves the day to the next one. */
  public function after(Response $response): Response
  {
    return $this->backup->daily && $response->after === null && $response->file === null && $this->due()
      ? $response->after($this->run(...)) : $response;
  }

  public function run(): void
  {
    $this->backup->locked(function (): void {
      if (!$this->due()) {
        return;
      }
      $today = $this->today();
      $this->backup->record(new DailyReport($today, 'the run did not finish', false));
      $this->backup->record($this->attempt($today));
    });
  }

  private function attempt(string $today): DailyReport
  {
    $reason = '';
    foreach ([new Ladder(), new Ladder(weeks: 0), new Ladder(days: 3, weeks: 0)] as $level => $ladder) {
      $level === 0 || $this->backup->thin($ladder);
      try {
        $this->backup->take(Occasion::Daily);

        return new DailyReport($today, null, $level > 0);
      } catch (SnapshotFailed $failure) {
        $reason = $failure->getMessage();
      }
    }

    return new DailyReport($today, $reason, true);
  }

  private function due(): bool
  {
    return $this->backup->report()?->day !== $this->today();
  }

  private function today(): string
  {
    return $this->clock->now()->setTimezone($this->clock->zone)->format('Y-m-d');
  }
}
