<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* A type name or URL that is not one of the thirty. */
final class UnknownType extends \RuntimeException
{
  public static function named(string $type): self
  {
    return new self(sprintf('no such type in the profile: %s', $type));
  }
}
