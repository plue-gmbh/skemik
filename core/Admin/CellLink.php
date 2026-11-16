<?php

declare(strict_types=1);

namespace Admin;

/* One word of a cell and where it leads, drawn as a link where the reader does not edit the field: a link
   field's address, or a row's name and its address, a call to action and its target. */
final readonly class CellLink
{
  public function __construct(
    public string $word,
    public ?string $href,
  ) {}

  /* A web address and the install's own path open beside the admin; a mail or a call opens its program. */
  public function opensBeside(): bool
  {
    return preg_match('#^(https?://|/)#i', (string) $this->href) === 1;
  }
}
