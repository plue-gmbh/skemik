<?php

declare(strict_types=1);

namespace Admin;

/* One action as Last changed and the log screen list it: the record as the
   table draws it, none for an account, a key, a language or what is gone, its
   name, what was done to it, who did it, by the id of the person or the key,
   and when, as a glance reads it, in full and as the house's wall time. A line
   with no record draws its glyph where a record's draws its state, so every name
   starts at one indent: a key's as the Grants card does, an account's its
   person, what is gone as a chip draws it, and anything else the action's.
   A key's name comes bare, as the one changed or the one changing.
   What the log kept of a record's former address and numbers, each `from → to`,
   is listed for the log screen, with the row's id a redaction names. */
final readonly class Change
{
  /** @param list<string> $kept */
  public function __construct(
    public ?SheetRow $row,
    public string $label,
    public ?string $href,
    public string $what,
    public string $who,
    public ?string $by,
    public string $ago,
    public string $stamp,
    public string $when,
    public string $glyph = 'activity',
    public array $kept = [],
    public ?string $activity = null,
    public bool $byKey = false,
  ) {}
}
