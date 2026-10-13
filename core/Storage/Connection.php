<?php

declare(strict_types=1);

namespace Storage;

/* Opens the PDO handle, with the same pragmas on every connection and no
   exception: SQLite forgets them when the handle closes. `foreign_keys` is what
   keeps dangling edges out, WAL is what makes concurrent readers survive a
   writer, and the busy timeout is what makes two editors survive each other:
   five seconds of waiting, and a save waiting longer is an error, since a lock
   held that long is a fault and not a colleague. `synchronous = normal` can lose
   the last commit to a power cut and never damages the file, which WAL allows
   and which a house's saves can afford for the fsync each would cost.
   `secure_delete` overwrites what a purge deletes, since a freed page keeps its
   bytes until something reuses it and a copy of `holdings/` carries them: some
   builds of SQLite default it on, a house's hoster's need not. */
final readonly class Connection
{
  private const PRAGMAS = [
    'foreign_keys = on',
    'journal_mode = wal',
    'busy_timeout = 5000',
    'synchronous = normal',
    'secure_delete = on',
  ];

  public function open(string $path): \PDO
  {
    $db = new \PDO('sqlite:' . $path, options: [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_EMULATE_PREPARES => false,
      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
    foreach (self::PRAGMAS as $pragma) {
      $db->exec('pragma ' . $pragma);
    }
    assert((int) $db->query('pragma foreign_keys')->fetchColumn() === 1, 'foreign keys are off');
    assert((int) $db->query('pragma secure_delete')->fetchColumn() === 1, 'a purge leaves its bytes in the file');

    return $db;
  }
}
