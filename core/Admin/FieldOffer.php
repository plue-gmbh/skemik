<?php

declare(strict_types=1);

namespace Admin;

use Profile\FieldKind;

/* One line of the Fields dialog: a field the type still offers, or a value
   object as the one entry it is, named by its first key and counted, and said
   to be computed where nobody types it. */
final readonly class FieldOffer
{
  public function __construct(
    public string $key,
    public string $label,
    public FieldKind $kind,
    public string $property,
    public int $fields,
    public bool $computed,
  ) {}
}
