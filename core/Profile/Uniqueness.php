<?php

declare(strict_types=1);

namespace Profile;

/* A `!` on a field: a second row of the type may not repeat this value, or
   one within the same edge — an order number is unique per seller, an
   invoice number per the organisation that issued it.
   Not an enum, because the scope names an edge and edge keys are not a closed
   set. It says only that a duplicate is refused: Skemik generates no numbers
   and checks no series, which is the house's own obligation. */
final readonly class Uniqueness
{
  private function __construct(public ?string $edge) {}

  public static function perType(): self
  {
    return new self(null);
  }

  public static function perEdge(string $key): self
  {
    return new self($key);
  }
}
