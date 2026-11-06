<?php

declare(strict_types=1);

namespace Storage;

/* Opens the PDO handle with the same pragmas on every connection, since SQLite
   forgets them when the handle closes. `foreign_keys` keeps dangling edges out,
   WAL lets readers survive a writer, and the busy timeout lets two editors
   survive each other: five seconds, since a lock held longer is a fault.
   `synchronous = normal` can lose the last commit to a power cut and never
   damages the file under WAL, which saves can afford for the fsync each would
   cost. `secure_delete` overwrites what a purge deletes, since a freed page
   keeps its bytes until reused and a copy of `holdings/` carries them: some
   builds of SQLite default it on, a host's need not. */
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
