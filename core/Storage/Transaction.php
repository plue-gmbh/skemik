<?php

declare(strict_types=1);

namespace Storage;

use Domain\Entity;

/* One write that checks and then writes, as a unit. `begin immediate` takes the
   write lock before the first read, so a check cannot be overtaken by another
   request between reading and writing. Inside a caller's transaction it joins
   that one, which is how a record's save carries its edges and its account guard. */
final readonly class Transaction
{
  public function run(\PDO $db, \Closure $work): void
  {
    if ($db->inTransaction()) {
      $work();

      return;
    }
    $db->exec('begin immediate');
    try {
      $work();
      $db->commit();
    } catch (\Throwable $failure) {
      $db->rollBack();
      throw $failure;
    }
  }

  /* The same, answering the record the work saved.
     @param \Closure(): Entity $work */
  public function saved(\PDO $db, \Closure $work): Entity
  {
    $saved = null;
    $this->run($db, function () use ($work, &$saved): void {
      $saved = $work();
    });

    return $saved instanceof Entity ? $saved : throw new \LogicException('a save that wrote nothing');
  }
}
