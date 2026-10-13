<?php

declare(strict_types=1);

namespace Admin;

use Http\Logger;
use Support\Json;
use Support\JsonFailed;

/* `log/app.log` and its rotations read newest entry first, backwards in chunks
   and never whole: five rotations of 8 MB are not a size to hold in memory on
   the hosting this runs on. A line that is no entry, the half a full disk cut
   off, is passed over rather than hiding the ones before it. Opening the log
   touches `seen` beside it, so a failure is news until the owner read it and
   not until rotation drops it; a file and no setting, since a snapshot carries
   the holdings and never the log. */
final readonly class ErrorLog
{
  private const CHUNK = 65_536;
  private const SEEN = 'seen';

  public function __construct(private string $directory, private Json $json = new Json()) {}

  /** @return list<ErrorEntry> newest first, up to $limit, those whose incident holds $incident where one is named */
  public function entries(int $limit, string $incident = ''): array
  {
    $found = [];
    foreach ($this->lines() as $line) {
      if (count($found) === $limit) {
        break;
      }
      $entry = str_contains($line, $incident) ? $this->entry($line) : null;
      if ($entry !== null && str_contains($entry->incident, $incident)) {
        $found[] = $entry;
      }
    }

    return $found;
  }

  /* Whether the newest failure was logged after the log was last opened; one without a time, until it was opened. */
  public function unseen(): bool
  {
    $latest = $this->entries(1)[0] ?? null;
    $seen = is_file($this->directory . '/' . self::SEEN) ? filemtime($this->directory . '/' . self::SEEN) : false;

    return $latest !== null && ($seen === false || ($latest->time?->getTimestamp() ?? 0) > $seen);
  }

  public function see(): void
  {
    is_dir($this->directory) && is_writable($this->directory) && touch($this->directory . '/' . self::SEEN);
  }

  private function entry(string $line): ?ErrorEntry
  {
    try {
      return ErrorEntry::read($this->json->decode($line));
    } catch (JsonFailed) {
      return null;
    }
  }

  /** @return \Generator<int, string> */
  private function lines(): \Generator
  {
    foreach (range(0, Logger::KEEP) as $number) {
      $path = $this->directory . '/' . Logger::FILE . ($number === 0 ? '' : '.' . $number);
      if (is_file($path) && is_readable($path)) {
        yield from $this->backwards($path);
      }
    }
  }

  /* The first piece of each chunk may be the end of a line the next chunk
     holds the start of, so it waits for that chunk.
     @return \Generator<int, string> */
  private function backwards(string $path): \Generator
  {
    $handle = fopen($path, 'rb');
    try {
      [$position, $rest] = [(int) filesize($path), ''];
      while ($position > 0) {
        $size = min(self::CHUNK, $position);
        $position -= $size;
        fseek($handle, $position);
        $pieces = explode("\n", fread($handle, $size) . $rest);
        $rest = (string) array_shift($pieces);
        yield from array_filter(array_reverse($pieces), fn (string $piece): bool => $piece !== '');
      }
      if ($rest !== '') {
        yield $rest;
      }
    } finally {
      fclose($handle);
    }
  }
}
