<?php

declare(strict_types=1);

namespace Admin;

/* One record pointing at the one on screen, and the field it points with: a row
   of the record screen's Points here, read off the edges and never kept. A work
   whose translations point with the same field is one row, named by one of them,
   with every language a link, since three rows the same to the letter say less.
   A labelled thing holding words in more than one language links each of them
   the same way, marked as words, being one record. */
final readonly class Pointer
{
  /** @param array<string, string> $locales each translation's or each language's words' address by its locale */
  public function __construct(
    public string $glyph,
    public string $label,
    public string $state,
    public string $collection,
    public string $field,
    public ?string $href,
    public array $locales = [],
    public bool $worded = false,
  ) {}

  /* What the row stands for in a count, which counts records and not rows. */
  public function records(): int
  {
    return $this->worded ? 1 : max(1, count($this->locales));
  }
}
