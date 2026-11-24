<?php

declare(strict_types=1);

namespace Admin;

/* One value narrowing the collection, a chip beside the query bar, and the address without it:
   Needs a look links filters the bar's select does not offer, and one nobody can see is one nobody
   can take away. `worded` says the value names itself, a status or a closed set's word, so the chip
   shows it alone and keeps the field's name for its tooltip; a number or a text stands behind its
   field's name. */
final readonly class Narrowed
{
  public function __construct(
    public string $value,
    public string $without,
    public string $field,
    public bool $worded,
  ) {}
}
