<?php

declare(strict_types=1);

namespace Admin;

use Auth\GrantCapability;

/* One capability the issue form offers, as what its verb reaches, the verb
   standing before it in bold, since what is chosen among four boxes is the verb
   and not the key. `note` is a tooltip on the box, and read aloud with it, where the box
   means more than it says: under a `read`
   nothing gates — on a type with no public state and no subject, which reads
   every row — and under a `create`, that a record naming a draft elsewhere needs
   the same key to read there, so a setup is one key with every rule (`Ingest\Edges`).
   `unpublished` is the choice under a `read` a status does gate: to read past it
   (`Auth\Grant`). */
final readonly class GrantOffer
{
  public function __construct(
    public GrantCapability $capability,
    public string $sentence,
    public ?string $note = null,
    public ?string $unpublished = null,
  ) {}
}
