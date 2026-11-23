<?php

declare(strict_types=1);

namespace Admin;

/* One line of the home screen's saved questions: its figure as the reader's
   scope counts it, and the narrowed collection it opens. */
final readonly class QuestionLine
{
  public function __construct(
    public string $id,
    public string $name,
    public string $figure,
    public string $href,
    public string $glyph,
    public string $collection,
  ) {}
}
