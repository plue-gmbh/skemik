<?php

declare(strict_types=1);

namespace Domain\Exceptions;

use Domain\Status;

/* A state the type cannot hold, or a change the record cannot take, carrying
   what it names so a colleague's screen can word it. */
final class ForbiddenTransition extends \RuntimeException
{
  private function __construct(
    string $message,
    public readonly string $named,
    public readonly ?Status $status = null,
  ) {
    parent::__construct($message);
  }

  public static function notHeld(Status $status, string $type): self
  {
    return new self(sprintf('%s has no state %s', $type, $status->value), $type, $status);
  }

  public static function fileKept(string $key): self
  {
    return new self(sprintf('%s is the uploaded file\'s own; upload a new file instead', $key), $key);
  }
}
