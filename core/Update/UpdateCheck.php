<?php

declare(strict_types=1);

namespace Update;

use Support\Clock;
use Support\Fetch;
use Support\FetchFailed;
use Support\Json;
use Support\JsonFailed;

/* The check for a new version, once an hour, and the report it and the install
   leave (`UpdateReport`). The manifest is read with nothing that identifies the
   install: no id, no domain, no version, no counts, no query string, and the
   static `User-Agent` every fetch of the house sends, so the address the request
   comes from is all that leaves it and the comparison is local; a personalised
   manifest is impossible and not merely unused, which is why the manifest is a
   static file served with request logging off, and why installs we cannot
   count are what *no further processor* rests on. A check that fails is a
   sentence on the owner's screen and nothing else changes. */
final readonly class UpdateCheck
{
  public const FILE = 'update.json';
  private const SECONDS = 3600;
  private const STALE = 86400;

  public function __construct(
    private string $data,
    private Fetch $fetch,
    private Clock $clock,
    private Json $json = new Json(),
  ) {}

  public function due(): bool
  {
    return $this->olderThan(self::SECONDS);
  }

  /* The hour is the least gap between checks and never a promise, since only a request starts one: a quiet
     night leaves the last one hours old, and only a day without one says that checks have stopped. */
  public function stale(): bool
  {
    return $this->olderThan(self::STALE);
  }

  public function report(): UpdateReport
  {
    $path = $this->data . '/' . self::FILE;
    try {
      return is_file($path) ? UpdateReport::from($this->json->readFile($path))->against(Version::APP)
        : new UpdateReport();
    } catch (JsonFailed) {
      return new UpdateReport();
    }
  }

  /* A report that could not be written leaves the last one standing, and the next check writes again. */
  public function record(UpdateReport $report): void
  {
    try {
      $this->json->writeFile($this->data . '/' . self::FILE, $report->toArray());
    } catch (JsonFailed) {
      return;
    }
  }

  public function manifest(): Manifest
  {
    try {
      $answer = $this->fetch->get(Release::ADDRESS);
      if ($answer->status !== 200) {
        throw UpdateRefused::manifest('answered ' . $answer->status);
      }

      return Manifest::read($this->json->decode($answer->body), Release::ADDRESS);
    } catch (FetchFailed | JsonFailed $failure) {
      throw UpdateRefused::manifest('could not be read: ' . $failure->getMessage());
    }
  }

  private function olderThan(int $seconds): bool
  {
    $checked = $this->report()->checked;

    return $checked === null
      || $this->clock->now()->getTimestamp() - (new \DateTimeImmutable($checked))->getTimestamp() >= $seconds;
  }
}
