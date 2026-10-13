<?php

declare(strict_types=1);

namespace Admin;

/* One line of Needs a look: how many, and where it opens, with the collection
   and the number of others it also stands in where it opens one. */
final readonly class Finding
{
  public function __construct(
    public Overlooked $look,
    public int $count,
    public ?string $href,
    public string $glyph,
    public string $collection,
    public int $others,
  ) {}
}
