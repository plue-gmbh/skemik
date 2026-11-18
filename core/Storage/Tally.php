<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;

/* One type's rows a finding of `Oversights` counts, and the oldest of them, which
   is the record a line counting one opens. */
final readonly class Tally
{
  public function __construct(public int $count, public EntityId $first) {}
}
