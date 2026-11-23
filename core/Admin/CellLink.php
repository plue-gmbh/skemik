<?php

declare(strict_types=1);

namespace Admin;

/* One word of a cell and where it leads, drawn as a link where the reader does not edit the field: a link
   field's address, or a row's name and its address, a call to action and its target, or the record a row
   names, an order line's product. A row's value stands beside its name, null where the row holds none. The
   language is the record's where the word is its text, null where it is an option's word, the chrome's. */
final readonly class CellLink
{
  public function __construct(
    public string $word,
    public ?string $href,
    public ?string $value = null,
    public bool $record = false,
    public ?string $lang = null,
  ) {}

  /* A web address and the install's own path open beside the admin; a mail or a call opens its program, and a
     record opens in place, as its chip does. */
  public function opensBeside(): bool
  {
    return !$this->record && preg_match('#^(https?://|/)#i', (string) $this->href) === 1;
  }
}
