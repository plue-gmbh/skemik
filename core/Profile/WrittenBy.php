<?php

declare(strict_types=1);

namespace Profile;

/* Who writes a value. A third value is the same
   conversation as a further FieldKind. */
enum WrittenBy: string
{
  case Authored = 'authored';   // a person typed it — the default, and nearly every field
  case Derived = 'derived';     // computed by Feed\JsonLd at emit, never stored

  /* Only authored fields get an editor input. */
  public function hasEditor(): bool
  {
    return $this === self::Authored;
  }

  /* Nothing is saved for a derived field, so the Validator never sees one. */
  public function isStored(): bool
  {
    return $this !== self::Derived;
  }
}
