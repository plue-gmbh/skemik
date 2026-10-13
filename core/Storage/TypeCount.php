<?php

declare(strict_types=1);

namespace Storage;

/* One type's rows in the holdings: all of them, trashed left out, and those
   public now by `Query::PUBLIC`, the clock's reading and not a stored flag. */
final readonly class TypeCount
{
  public function __construct(public string $type, public int $rows, public int $public) {}
}
