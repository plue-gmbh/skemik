<?php

declare(strict_types=1);

namespace Storage;

/* Moves a database to `Schema`'s shape by the numbered scripts above its
   `pragma user_version`, one transaction each: one integer is the whole record,
   since scripts are forward-only and never edited once released, and it travels
   with a snapshot. A script returns a closure, since moving data is never
   derivable from two shapes, and never derives: `sort_keys` and `words` are
   written again after it by the release's own code. Squashing is refused, since
   an install midway could not replay a squashed file. Foreign keys are off while
   scripts run, as SQLite's table rebuild asks, set here because the pragma is
   ignored inside a transaction, and `foreign_key_check` stands in before each
   commit. */
final readonly class Migrator
{
  private const SCRIPT = '/^(\d{4})_[a-z0-9_]+\.php$/';

  public function __construct(private Schema $schema, private string $scripts) {}

  /* A new database takes the declaration whole, stamped with the last script's
     number: every script reaches that shape, so replaying them over it would
     make tables that stand. So what a script writes into rows, a founding writes
     itself. One a run before already shaped is left as it is. */
  public function prepare(\PDO $db): void
  {
    if ($this->schema->differences($db) !== []) {
      $this->schema->create($db, self::last($this->scripts));
    }
  }

  /* The copy goes to `$copyTo`, written with `vacuum into` so it is one settled
     file without the WAL beside it, or nowhere where the caller restores a
     snapshot it keeps. */
  public function migrate(\PDO $db, ?string $copyTo): void
  {
    $from = $this->number($db);
    $pending = array_filter($this->scripts(), fn (int $number): bool => $number > $from, ARRAY_FILTER_USE_KEY);
    if ($pending !== [] && $copyTo !== null) {
      $db->prepare('vacuum into ?')->execute([$copyTo . '.part']);
      rename($copyTo . '.part', $copyTo) || throw SchemaFailed::uncopied($copyTo);
    }
    $db->exec('pragma foreign_keys = off');
    try {
      foreach ($pending as $number => $path) {
        $this->run($db, $number, $path);
      }
    } finally {
      $db->exec('pragma foreign_keys = on');
    }
    $differences = $this->schema->differences($db);
    if ($differences !== []) {
      throw SchemaFailed::differs($differences);
    }
  }

  /* A number past the last script is a later release's, whose data-only
     scripts leave no shape this code could tell from its own. */
  private function number(\PDO $db): int
  {
    $number = (int) $db->query('pragma user_version')->fetchColumn();
    $last = self::last($this->scripts);
    if ($number > $last) {
      throw SchemaFailed::ahead($number, $last);
    }

    return $number;
  }

  private function run(\PDO $db, int $number, string $path): void
  {
    $migration = require $path;
    if (!$migration instanceof \Closure) {
      throw SchemaFailed::unreadableScript($path);
    }
    $db->beginTransaction();
    try {
      $migration($db);
      if ($db->query('pragma foreign_key_check')->fetch() !== false) {
        throw SchemaFailed::danglingKeys($path);
      }
      $db->exec('pragma user_version = ' . $number);
      $db->commit();
    } catch (\Throwable $failure) {
      $db->rollBack();
      throw $failure;
    }
  }

  /* The schema version the holdings are at once moved, read without building the schema. */
  public static function last(string $scripts): int
  {
    return array_key_last(self::numbered($scripts)) ?? 0;
  }

  /** @return array<int, string> path by number, in order */
  private function scripts(): array
  {
    return self::numbered($this->scripts);
  }

  /** @return array<int, string> path by number, in order */
  private static function numbered(string $directory): array
  {
    $scripts = [];
    foreach (glob($directory . '/*.php') ?: [] as $path) {
      if (preg_match(self::SCRIPT, basename($path), $match) === 1) {
        $scripts[(int) $match[1]] = $path;
      }
    }
    ksort($scripts);
    assert(array_keys($scripts) === range(1, count($scripts)) || $scripts === [], 'a script number is skipped');

    return $scripts;
  }
}
