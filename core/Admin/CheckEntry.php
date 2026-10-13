<?php

declare(strict_types=1);

namespace Admin;

/* One finding of the holdings check as its screen draws it (`ChecksController`):
   what it names, a record or the house, and the sentence of what does not fit. */
final readonly class CheckEntry
{
  public function __construct(
    public ?string $href,
    public string $glyph,
    public string $name,
    public string $collection,
    public string $sentence,
  ) {}
}
