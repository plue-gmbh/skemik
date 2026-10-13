<?php

declare(strict_types=1);

namespace Admin;

/* One record pointing at the one on screen, and the field it points with: a row
   of the record screen's Points here, read off the edges and never kept. */
final readonly class Pointer
{
  public function __construct(
    public string $glyph,
    public string $label,
    public string $state,
    public string $collection,
    public string $field,
    public ?string $href,
  ) {}
}
