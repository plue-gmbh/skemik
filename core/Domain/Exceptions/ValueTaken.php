<?php

declare(strict_types=1);

namespace Domain\Exceptions;

use Domain\Violation;

/* A `!` value another record already holds in the same scope (`Profile\Uniqueness`).
   It carries the violation, so the refusal reaches a person as the field it names
   rather than as a constraint error. */
final class ValueTaken extends \RuntimeException
{
  public function __construct(public readonly Violation $violation)
  {
    parent::__construct(sprintf('%s: %s', $violation->field, $violation->said()));
  }
}
