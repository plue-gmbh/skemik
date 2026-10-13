<?php

declare(strict_types=1);

namespace Update;

use Storage\CollectionStore;
use Storage\Connection;
use Storage\Migrator;
use Storage\Rewrite;
use Support\Halt;
use Support\Words;

/* The half of an update that stays on the machine: the first request on a version
   or a last script the data folder is not stamped with moves the holdings to the
   running code, so an operator updates by copying a release, and a copy between
   releases that brought a script moves too. The stamp is a file and not a row,
   read beside a listing of `migrations/` on every request. The marker is the lock, so a request meeting a
   move answers *being updated* and one after a crash resumes it, the scripts done
   being skipped and the rewrite idempotent. A failed move goes back to the copy
   and leaves its reason in the marker alone, an exception's words holding a path
   the page would show every stranger, and stops every address, since a retry per
   request would copy the database each time and a fix is a release. PHP's time
   limit is lifted, or a host's limit mid-rewrite would leave the move to the next
   request and the next. */
final readonly class Move
{
  public const STAMP = 'version.txt';
  public const MARKER = 'updating.txt';
  private const COPY = 'holdings/data.sqlite.copy';

  /** @param \Closure(): Migrator $migrator
      @param \Closure(\PDO): CollectionStore $collections
      @param \Closure(\PDO): Rewrite $rewriting */
  public function __construct(
    private string $data,
    private string $scripts,
    private Connection $connection,
    private \Closure $migrator,
    private \Closure $collections,
    private \Closure $rewriting,
    private Words $words,
  ) {}

  /* What stops every address, or null once the holdings are at the running version. */
  public function halt(): ?Halt
  {
    if (!is_file($this->data . '/' . self::MARKER) && $this->current()) {
      return null;
    }
    if (self::left($this->data, Version::APP) !== null) {
      return $this->failed();
    }
    $marker = fopen($this->data . '/' . self::MARKER, 'c');
    if ($marker === false || !flock($marker, LOCK_EX | LOCK_NB)) {
      return new Halt($this->words->get('boot.updating'), passes: true);
    }
    try {
      return $this->current() ? $this->done() : $this->locked($marker);
    } finally {
      flock($marker, LOCK_UN);
      fclose($marker);
    }
  }

  /* A restore's copy before it is swapped in, on the connection the restore opened. */
  public function moved(\PDO $db): void
  {
    ($this->migrator)()->migrate($db, null);
    ($this->collections)($db)->provide();
    ($this->rewriting)($db)->all();
    $this->stamp();
  }

  /** @param resource $marker */
  private function locked($marker): ?Halt
  {
    ftruncate($marker, 0);
    fwrite($marker, Version::APP . "\n");
    /* A host that disables it would log a warning here on every move. */
    function_exists('set_time_limit') && set_time_limit(0);
    $copy = $this->data . '/' . self::COPY;
    is_file($copy) && unlink($copy);
    try {
      $this->move($copy);
    } catch (\Throwable $failure) {
      $this->back($copy);
      fwrite($marker, $failure->getMessage() . "\n");

      return $this->failed();
    }
    is_file($copy) && unlink($copy);
    $this->stamp();

    return $this->done();
  }

  private function failed(): Halt
  {
    return new Halt($this->words->get('boot.update_failed', ['version' => Version::APP, 'file' => self::MARKER]));
  }

  /* Moved, by this request or by the one that held the lock meanwhile: the marker goes. */
  private function done(): null
  {
    is_file($this->data . '/' . self::MARKER) && unlink($this->data . '/' . self::MARKER);

    return null;
  }

  /* The connection is this method's alone, so it is closed on return and the copy can take the file's place. */
  private function move(string $copy): void
  {
    $db = $this->connection->open($this->data . '/holdings/data.sqlite');
    ($this->migrator)()->migrate($db, $copy);
    ($this->collections)($db)->provide();
    ($this->rewriting)($db)->all();
  }

  /* The copy is whole or absent (`Migrator`); the WAL beside the live file belongs to what failed. */
  private function back(string $copy): void
  {
    if (!is_file($copy)) {
      return;
    }
    $live = $this->data . '/holdings/data.sqlite';
    foreach (['-wal', '-shm'] as $suffix) {
      is_file($live . $suffix) && unlink($live . $suffix);
    }
    rename($copy, $live) || throw new \RuntimeException('the copy could not take the database\'s place');
  }

  /* Whether the data folder's holdings are at a version, read by an update of
     the one it brought in (`Updater`), which cannot know that release's scripts. */
  public static function stamped(string $data, string $version): bool
  {
    $stamp = $data . '/' . self::STAMP;

    return is_file($stamp) && strtok((string) file_get_contents($stamp), "\n") === $version;
  }

  private function current(): bool
  {
    $stamp = $this->data . '/' . self::STAMP;

    return is_file($stamp) && file_get_contents($stamp) === $this->stamping();
  }

  private function stamp(): void
  {
    file_put_contents($this->data . '/' . self::STAMP, $this->stamping());
  }

  private function stamping(): string
  {
    return Version::APP . "\n" . Migrator::last($this->scripts) . "\n";
  }

  /* The reason a move of a version left, or null: another version's is history. */
  public static function left(string $data, string $version): ?string
  {
    $marker = $data . '/' . self::MARKER;
    $lines = is_file($marker) ? explode("\n", (string) file_get_contents($marker), 2) : [];

    return count($lines) === 2 && $lines[0] === $version && trim($lines[1]) !== '' ? trim($lines[1]) : null;
  }
}
