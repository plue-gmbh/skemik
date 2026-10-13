<?php

declare(strict_types=1);

namespace Domain;

/* What failed, and where. A list of these is an answer; a bool is not. A
   finding about a stored row names the row, so a screen can link to it rather
   than parse a reason. */
final readonly class Violation
{
  public const REQUIRED = 'required';

  public function __construct(
    public string $field,
    public string $reason,
    public ?EntityId $record = null,
  ) {}
}
