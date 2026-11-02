<?php

declare(strict_types=1);

namespace Admin;

use Profile\FieldKind;

/* One line of the Fields dialog: a field the type still offers, beside the name
   the address calls it, and said to be computed where nobody types it. */
final readonly class FieldOffer
{
  public function __construct(
    public string $key,
    public string $label,
    public FieldKind $kind,
    public string $property,
    public bool $computed,
  ) {}
}
