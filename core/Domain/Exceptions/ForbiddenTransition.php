<?php

declare(strict_types=1);

namespace Domain\Exceptions;

use Domain\Status;

/* A state the type cannot hold, or a change the record cannot take. */
final class ForbiddenTransition extends \RuntimeException
{
  public static function notHeld(Status $status, string $type): self
  {
    return new self(sprintf('%s has no state %s', $type, $status->value));
  }

  public static function fileKept(string $key): self
  {
    return new self(sprintf('%s is the uploaded file\'s own; upload a new file instead', $key));
  }
}
