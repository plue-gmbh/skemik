<?php

declare(strict_types=1);

namespace Admin;

use Domain\Locale;
use Profile\TypeDefinition;
use Storage\Collection;

/* The records of one type pointing with one field at the record on screen: what every row of theirs in
   Points here shares, read in the screen's locale. `target` is the record as a filter names it,
   `places/north-hall`. */
final readonly class PointerGroup
{
  public function __construct(
    public TypeDefinition $type,
    public string $predicate,
    public string $field,
    public ?Collection $holder,
    public string $target,
    public Locale $in,
  ) {}

  /* The holder's list filtered to the record on screen, where the rows not drawn stand. */
  public function filtered(): ?string
  {
    return $this->holder === null ? null
      : '/c/' . $this->holder->slug->value . '?' . http_build_query([$this->predicate => $this->target]);
  }
}
