<?php

declare(strict_types=1);

namespace Update;

use Storage\Admission;
use Storage\Connection;
use Support\Clock;

/* Puts a snapshot back whole, never the database or the media alone, since a
   half-restored pair is the state a snapshot of both exists to make
   unreachable, and who may get in as the house has it now (`Storage\Admission`).
   The copy is built beside the live folder first, since the snapshot of the live
   holdings, *before the restore*, thins the ladder and may pass the very one
   being put back, and moved to the running version as a copied release is
   (`Move`), a snapshot being maybe older; then two renames swap it in. A marker
   halts every other request meanwhile (`Admin\Probes`), or a save landing between
   the snapshot and the swap would be lost without a trace, and a failure anywhere
   leaves the running install as it was. `sessions/` and `login/` are emptied,
   which no snapshot carries, so everybody logs in again. */
final readonly class Restore
{
  public const MARKER = 'restoring.txt';
  public const SECONDS = 3600;
  private const STAGED = 'holdings.restoring';
  private const DISPLACED = 'holdings.displaced';

  public function __construct(
    private Backup $backup,
    private string $data,
    private Connection $connection,
    private Move $move,
    private Admission $admission,
    private Clock $clock,
  ) {}

  /* `$owner` is the account restoring, which stays whatever the snapshot holds. */
  public function put(Snapshot $snapshot, string $owner): void
  {
    $holdings = $snapshot->path . '/holdings';
    if (!is_file($holdings . '/data.sqlite') || !is_dir($holdings . '/media')) {
      throw SnapshotFailed::incomplete($snapshot->name);
    }
    file_put_contents($this->data . '/' . self::MARKER, $this->clock->now()->format(Clock::STORED) . "\n");
    try {
      $this->stage($holdings);
      $this->backup->take(Occasion::Restore);
      $this->settle($owner);
      $this->swap();
      $this->emptied('sessions');
      $this->emptied('login');
    } finally {
      $this->backup->remove($this->data . '/' . self::STAGED);
      unlink($this->data . '/' . self::MARKER);
    }
  }

  /* The database is copied, not vacuumed: a snapshot's is settled already. */
  private function stage(string $from): void
  {
    $staged = $this->data . '/' . self::STAGED;
    $this->backup->remove($staged);
    $this->backup->linkTree($from . '/media', $staged . '/media');
    copy($from . '/data.sqlite', $staged . '/data.sqlite') || throw SnapshotFailed::restoring('copy the database');
  }

  /* On the copy's own connection, closed on return so its folder can be renamed. */
  private function settle(string $owner): void
  {
    $copy = $this->connection->open($this->data . '/' . self::STAGED . '/data.sqlite');
    $this->move->moved($copy);
    $this->admission->carry($copy, $this->data . '/holdings/data.sqlite', $owner);
  }

  private function swap(): void
  {
    [$live, $staged, $displaced] = [$this->data . '/holdings', $this->data . '/' . self::STAGED,
      $this->data . '/' . self::DISPLACED];
    $this->backup->remove($displaced);
    rename($live, $displaced) || throw SnapshotFailed::restoring('move the live holdings aside');
    if (!rename($staged, $live)) {
      rename($displaced, $live);
      throw SnapshotFailed::restoring('move the copy into place');
    }
    $this->backup->remove($displaced);
  }

  private function emptied(string $folder): void
  {
    foreach (glob($this->data . '/' . $folder . '/*') ?: [] as $file) {
      is_file($file) && unlink($file);
    }
  }
}
