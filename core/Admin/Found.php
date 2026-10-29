<?php

declare(strict_types=1);

namespace Admin;

/* One line of the search's page: a record by its label and slug in its state, or
   a line of the logs, a colleague's changes or an incident, which has neither. */
final readonly class Found
{
  public function __construct(
    public string $glyph,
    public string $label,
    public string $href,
    public string $note = '',
    public ?string $state = null,
  ) {}
}
