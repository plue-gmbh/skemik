<?php

declare(strict_types=1);

namespace Update;

use Domain\EntityId;
use Domain\Violation;
use Http\Response;
use Storage\HoldingsCheck;
use Storage\HoldingsReport;
use Storage\SettingStore;
use Support\Clock;

/* Runs `Storage\HoldingsCheck`, the half of the profile and claims tier that
   needs this house's rows, and keeps its report, which reverts nothing: only
   `Move`'s shape check takes an update back. `Profile\Claims` is the release's
   answer, the same in every house, so it is asked before a release and by no install. A
   pass a day, a slice every ten minutes after an answer nobody waits for until
   the cursor has come round, so three thousand records are read whole within
   the hour and no answer waits on a pass; the day counts from the pass's last
   slice. A mended record leaves at the next slice. A report another version
   wrote is started over at once, since the fields a release asks for ship with
   it, and Needs a look would otherwise count a field it took away for a day. */
final readonly class Checkup
{
  private const PASS_SECONDS = 86400;
  private const SLICE_SECONDS = 600;
  private const ROWS = 500;

  public function __construct(
    private HoldingsCheck $holdings,
    private SettingStore $settings,
    private Clock $clock,
    private Backup $backup,
  ) {}

  /* An answer that has work of its own, or streams a file, leaves the slice to the next one. */
  public function after(Response $response): Response
  {
    return $this->due() && $response->after === null && $response->file === null
      ? $response->after($this->run(...)) : $response;
  }

  /* Under the lock a snapshot, an update and a restore take, and asked again inside it: the time is stamped
     when a slice ends, so every answer that left while one ran would start its own, each holding a worker. */
  public function run(): void
  {
    $this->backup->locked(function (): void {
      if ($this->due()) {
        $this->sample();
      }
    });
  }

  private function due(): bool
  {
    $since = $this->clock->now()->getTimestamp() - ($this->settings->holdingsCheckedAt()?->getTimestamp() ?? 0);
    $wait = $this->settings->holdingsCursor() === null ? self::PASS_SECONDS : self::SLICE_SECONDS;

    return $since >= $wait || $this->settings->holdingsReportVersion() !== Version::APP;
  }

  private function sample(): void
  {
    $began = $this->clock->now();
    $last = $this->settings->holdingsReport();
    if ($last?->version !== Version::APP) {
      $this->settings->setHoldingsCursor(null);
    }
    $from = $this->settings->holdingsCursor();
    $found = $this->holdings->run(self::ROWS);
    $kept = $last?->version === Version::APP ? $this->kept($last, $from, $this->settings->holdingsCursor()) : [];
    $unique = [];
    foreach ([...$kept, ...$found] as $violation) {
      $unique[implode("\n", [$violation->field, $violation->said(), $violation->record?->value])] = $violation;
    }
    $this->settings->setHoldingsReport(new HoldingsReport(array_values($unique), $began, Version::APP));
  }

  /* What the last report said of the rows this slice did not read, those changed
     since it was written read again.
     @return list<Violation> */
  private function kept(HoldingsReport $last, ?string $from, ?string $to): array
  {
    $outside = array_filter($last->violations, fn (Violation $violation): bool
      => $violation->record !== null && !$this->within($violation->record, $from, $to));
    $records = array_values(array_filter(array_map(fn (Violation $violation): ?EntityId => $violation->record,
      $outside)));
    $changed = $this->holdings->changed($records, $last->at->format(Clock::STORED));
    $reread = array_map(fn (EntityId $id): string => $id->value, $changed);

    return [...array_filter($outside, fn (Violation $violation): bool
      => !in_array($violation->record?->value, $reread, true)), ...$this->holdings->rows($changed)];
  }

  /* The rows a slice read: after the cursor it started from, up to the one it
     left, or to the end where it wrapped. */
  private function within(EntityId $record, ?string $from, ?string $to): bool
  {
    return ($from === null || strcmp($record->value, $from) > 0)
      && ($to === null || strcmp($record->value, $to) <= 0);
  }
}
