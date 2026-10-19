<?php

declare(strict_types=1);

namespace Domain;

/* What failed, and where. A list of these is an answer; a bool is not. A
   finding about a stored row names the row, so a screen can link to it rather
   than parse a reason. A derived column an older version wrote is `STALE`, said
   apart from the rest since it is the code's doing and never the row's. */
final readonly class Violation
{
  public const REQUIRED = 'required';
  public const STALE = 'derived by an older version';

  public function __construct(
    public string $field,
    public string $reason,
    public ?EntityId $record = null,
  ) {}
}
