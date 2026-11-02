<?php

declare(strict_types=1);

namespace Admin;

/* What a column's popover knows about its field beyond the column: what the
   collection would refuse — removing a †, moving past an end. */
final readonly class FieldEntry
{
  public function __construct(
    public bool $removable,
    public bool $first,
    public bool $last,
  ) {}
}
