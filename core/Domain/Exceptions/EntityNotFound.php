<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* No row with this id or slug. */
final class EntityNotFound extends \RuntimeException
{
  public static function withId(string $id): self
  {
    return new self(sprintf('no record %s', $id));
  }
}
