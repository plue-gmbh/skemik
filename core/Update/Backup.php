<?php

declare(strict_types=1);

namespace Update;

use Support\Clock;
use Support\Json;
use Support\JsonFailed;

/* Writes a snapshot of `holdings/` into `backups/{stamp}-{occasion}/holdings/`,
   the same shape as the original: the database through `vacuum into`, one
   settled file and never a copy of the live WAL, the media tree by hardlink
   where the filesystem allows it and by copy where not. Whether it fits is
   learnt by writing it, since the free space PHP reports is the disk's and not
   a hoster's quota: a write that fails leaves nothing behind. Written under a
   `.part` name and renamed, so a listing never shows one half written; what
   the ladder has passed leaves after a new one and never before. The daily
   run's report lives beside the snapshots and outside `holdings/`, so a
   restore does not bring an old one back, and the copy holds no `rate_limit`
   row, so a house restored or moved after a burst of logins does not refuse
   its owner's for the window that burst opened. */
final readonly class Backup
{
  public const FOLDER = 'backups';
  private const STAMP = 'Y-m-d\TH-i-s\Z';
  private const NAME = '/^(\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2})Z-(daily|manual|restore|update)$/';
  private const REPORT = 'daily.json';
  private const LOCK = 'daily.lock';

  public function __construct(
    private string $data,
    private \PDO $db,
    private Clock $clock,
    public bool $daily,
    private Json $json = new Json(),
  ) {}

  public function folder(): string
  {
    return $this->data . '/' . self::FOLDER;
  }

  public function take(Occasion $occasion): Snapshot
  {
    $at = $this->free($occasion);
    $name = $at->format(self::STAMP) . '-' . $occasion->value;
    [$part, $folder] = [$this->folder() . '/' . $name . '.part', $this->folder() . '/' . $name];
    /* Outside the cleanup below, so a take racing this one for the second leaves the other's folder alone. */
    mkdir($part, 0700, true)
      || throw SnapshotFailed::writing($name, new \RuntimeException('could not make ' . $part));
    try {
      $this->linkTree($this->data . '/holdings/media', $part . '/holdings/media');
      $this->db->prepare('vacuum into ?')->execute([$part . '/holdings/data.sqlite']);
      $this->forgetRates($part . '/holdings/data.sqlite');
      rename($part, $folder) || throw new \RuntimeException('could not rename ' . $part);
    } catch (\Throwable $failure) {
      $this->remove($part);
      throw SnapshotFailed::writing($name, $failure);
    }
    $this->thin(new Ladder());

    return new Snapshot($name, $occasion, $at, $folder);
  }

  /** @return list<Snapshot> newest first */
  public function list(): array
  {
    $names = is_dir($this->folder()) ? scandir($this->folder(), SCANDIR_SORT_DESCENDING) ?: [] : [];

    return array_values(array_filter(array_map($this->snapshot(...), $names)));
  }

  public function named(string $name): ?Snapshot
  {
    $snapshot = $this->snapshot($name);

    return $snapshot !== null && is_dir($snapshot->path) ? $snapshot : null;
  }

  /* As `du` counts them: a hardlinked file counts in every snapshot holding it. */
  public function size(Snapshot $snapshot): int
  {
    $bytes = 0;
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($snapshot->path,
      \FilesystemIterator::SKIP_DOTS)) as $file) {
      $bytes += $file->getSize();
    }

    return $bytes;
  }

  public function thin(Ladder $ladder): void
  {
    foreach ($ladder->passed($this->list(), $this->clock->zone) as $snapshot) {
      $this->remove($snapshot->path);
    }
  }

  /* One that cannot be read is none, so the day's run writes a whole one again: it is read while every answer
     is built, and a failure page on every address is no price for a lost line of the backups screen. */
  public function report(): ?DailyReport
  {
    $path = $this->folder() . '/' . self::REPORT;
    try {
      $read = is_file($path) ? $this->json->readFile($path) : [];
    } catch (JsonFailed) {
      return null;
    }

    return is_string($read['day'] ?? null) ? new DailyReport($read['day'],
      is_string($read['failed'] ?? null) ? $read['failed'] : null, ($read['shrunk'] ?? false) === true) : null;
  }

  public function record(DailyReport $report): void
  {
    is_dir($this->folder()) || mkdir($this->folder(), 0700);
    try {
      $this->json->writeFile($this->folder() . '/' . self::REPORT, ['day' => $report->day,
        'failed' => $report->failed, 'shrunk' => $report->shrunk]);
    } catch (JsonFailed $failure) {
      throw SnapshotFailed::writing(self::REPORT, $failure);
    }
  }

  /* Runs the work while holding the lock, or not at all while another request holds it. */
  public function locked(\Closure $work): bool
  {
    is_dir($this->folder()) || mkdir($this->folder(), 0700);
    $handle = fopen($this->folder() . '/' . self::LOCK, 'c');
    if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
      return false;
    }
    try {
      $work();
    } finally {
      flock($handle, LOCK_UN);
      fclose($handle);
    }

    return true;
  }

  /* The media tree, each file linked to the original and copied where the
     filesystem refuses links: an upload is never changed in place, so a link is
     a copy that costs nothing. A house without an upload yet has no tree. */
  public function linkTree(string $from, string $to): void
  {
    mkdir($to, 0700, true) || throw new \RuntimeException('could not make ' . $to);
    foreach (is_dir($from) ? scandir($from) ?: [] : [] as $name) {
      [$source, $target] = [$from . '/' . $name, $to . '/' . $name];
      if ($name === '.' || $name === '..') {
        continue;
      }
      if (is_dir($source)) {
        $this->linkTree($source, $target);
      } elseif (!$this->linked($source, $target) && !copy($source, $target)) {
        throw new \RuntimeException('could not copy ' . $source);
      }
    }
  }

  public function remove(string $path): void
  {
    if (!is_dir($path)) {
      return;
    }
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
  }

  /* A refused link is the filesystem's answer and not a warning for the host's log,
     and a host that disables the function has answered for every file. */
  private function linked(string $from, string $to): bool
  {
    if (!function_exists('link')) {
      return false;
    }
    set_error_handler(fn (): bool => true);
    try {
      return link($from, $to);
    } finally {
      restore_error_handler();
    }
  }

  /* The second it is taken in, or the next one no snapshot of the occasion holds:
     a name is to the second, and a double press, or an update's snapshot in the
     second of the one before it, would otherwise find its folder taken. */
  private function free(Occasion $occasion): \DateTimeImmutable
  {
    $now = $this->clock->now();
    $at = $now->setTime((int) $now->format('G'), (int) $now->format('i'), (int) $now->format('s'));
    $path = fn (\DateTimeImmutable $at): string => $this->folder() . '/' . $at->format(self::STAMP) . '-'
      . $occasion->value;
    while (file_exists($path($at)) || file_exists($path($at) . '.part')) {
      $at = $at->modify('+1 second');
    }

    return $at;
  }

  private function snapshot(string $name): ?Snapshot
  {
    if (preg_match(self::NAME, $name, $parts) !== 1) {
      return null;
    }
    $at = \DateTimeImmutable::createFromFormat('!Y-m-d\TH-i-s', $parts[1], new \DateTimeZone('UTC'));

    return $at === false ? null
      : new Snapshot($name, Occasion::from($parts[2]), $at, $this->folder() . '/' . $name);
  }

  /* Through a connection of its own, closed before the rename, so nothing beside the file stays. */
  private function forgetRates(string $database): void
  {
    $copy = new \PDO('sqlite:' . $database, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $copy->exec('delete from rate_limit');
  }
}
