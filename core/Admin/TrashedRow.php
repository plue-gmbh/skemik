<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;

/* One record in the trash as its screen draws it: when it went there, and what a
   purge would take along, counted before anybody confirms one. A `Person` may
   have acted, so only its row offers the log's name redaction. A held type is
   usually under a retention obligation: the dialog says so and refuses nothing,
   since an invoice sent by mistake is the owner's to remove. */
final readonly class TrashedRow
{
  public function __construct(
    public Entity $entity,
    public string $label,
    public string $since,
    public int $pointing,
    public bool $acted,
    public bool $held,
  ) {}
}
