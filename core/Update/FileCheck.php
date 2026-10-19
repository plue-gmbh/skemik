<?php

declare(strict_types=1);

namespace Update;

use Http\Response;
use Support\Clock;
use Support\Json;
use Support\JsonFailed;

/* The running tree against the `SHA256SUMS` its release ships, since the signature
   is checked on the archive alone and a hand edit or a release half copied would
   stand unseen. `.htaccess` is not listed, its lines outside the Skemik block being
   the hoster's. A file nobody listed counts where the folder is wholly ours, and at
   the root only as a `.php` beside `index.php` and `config.php`, the root holding the
   hoster's files and the data folder too. A tree with no list is not compared. Whoever
   can change the code can change the list and this reader, so it finds an accident and
   a careless intruder, never a careful one. Once a day, and at once under a release not
   compared yet, since a release copied by hand is the likeliest to be half copied; once
   an hour while a file differs, so a tree put right, or one compared while a copy was
   landing, turns green by itself. */
final readonly class FileCheck
{
  public const LIST = 'SHA256SUMS';
  private const OURS = ['core', 'view', 'language', 'migrations'];
  public const REPORT = 'files.json';
  private const LINE = '/^([0-9a-f]{64}) [ *](.+)$/';

  public function __construct(
    private string $root,
    private Backup $backup,
    private Clock $clock,
    private Json $json = new Json(),
  ) {}

  /* An answer that has work of its own, or streams a file, leaves the comparison to the next one. */
  public function after(Response $response): Response
  {
    return $response->after === null && $response->file === null && $this->due()
      ? $response->after($this->run(...)) : $response;
  }

  /* Under the lock an update takes, so no comparison reads a tree half swapped, and asked again inside it. */
  public function run(): void
  {
    $this->backup->locked(function (): void {
      if ($this->due()) {
        $this->record($this->compare());
      }
    });
  }

  public function compare(): FileReport
  {
    $at = $this->clock->now()->format(Clock::STORED);
    if (!is_file($this->root . '/' . self::LIST)) {
      return new FileReport($at, Version::APP, false);
    }
    $listed = $this->listed();
    $missing = array_keys(array_filter($listed, fn (string $hash, string $path): bool
      => !is_file($this->root . '/' . $path), ARRAY_FILTER_USE_BOTH));
    $changed = array_keys(array_filter($listed, fn (string $hash, string $path): bool
      => is_file($this->root . '/' . $path) && hash_file('sha256', $this->root . '/' . $path) !== $hash,
      ARRAY_FILTER_USE_BOTH));
    $added = array_values(array_diff($this->present(), array_keys($listed)));
    sort($changed);
    sort($missing);
    sort($added);

    return new FileReport($at, Version::APP, true, $changed, $missing, $added);
  }

  /* One that cannot be read is none, and so due, as `Backup::report` reads the daily one. */
  public function report(): ?FileReport
  {
    $path = $this->backup->folder() . '/' . self::REPORT;
    try {
      $read = is_file($path) ? $this->json->readFile($path) : [];
    } catch (JsonFailed) {
      return null;
    }
    if (!is_string($read['at'] ?? null) || !is_string($read['version'] ?? null)) {
      return null;
    }
    $paths = fn (string $kind): array => array_values(array_filter((array) ($read[$kind] ?? []), is_string(...)));

    return new FileReport($read['at'], $read['version'], ($read['listed'] ?? false) === true,
      $paths('changed'), $paths('missing'), $paths('added'));
  }

  public function record(FileReport $report): void
  {
    is_dir($this->backup->folder()) || mkdir($this->backup->folder(), 0700);
    $this->json->writeFile($this->backup->folder() . '/' . self::REPORT, ['at' => $report->at,
      'version' => $report->version, 'listed' => $report->listed, 'changed' => $report->changed,
      'missing' => $report->missing, 'added' => $report->added]);
  }

  private function due(): bool
  {
    $report = $this->report();
    if ($report === null || $report->version !== Version::APP) {
      return true;
    }
    $lasts = $report->differing() === [] ? '-1 day' : '-1 hour';

    return $report->at < $this->clock->now()->modify($lasts)->format(Clock::STORED);
  }

  /** @return array<string, string> each listed path with its hash */
  private function listed(): array
  {
    $listed = [];
    foreach (file($this->root . '/' . self::LIST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
      if (preg_match(self::LINE, $line, $parts) === 1) {
        $listed[$parts[2]] = $parts[1];
      }
    }

    return $listed;
  }

  /** @return list<string> the paths where a file nobody listed counts */
  private function present(): array
  {
    $found = array_diff(array_map(basename(...), glob($this->root . '/*.php') ?: []), ['config.php']);
    foreach (self::OURS as $folder) {
      if (!is_dir($this->root . '/' . $folder)) {
        continue;
      }
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/' . $folder,
        \FilesystemIterator::SKIP_DOTS));
      foreach ($files as $file) {
        $found[] = substr($file->getPathname(), strlen($this->root) + 1);
      }
    }

    return array_values($found);
  }
}
