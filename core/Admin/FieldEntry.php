<?php

declare(strict_types=1);

namespace Admin;

/* What a column's popover knows about the entry its field belongs to: the keys
   that move and leave together, the name the removal is confirmed under,
   and what the collection would refuse — removing a †, moving past an end. */
final readonly class FieldEntry
{
  /** @param list<string> $keys */
  public function __construct(
    public array $keys,
    public string $name,
    public bool $removable,
    public bool $first,
    public bool $last,
  ) {}
}
