<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* A value the spine cannot be made of: a tag that is not BCP-47, a slug that is
   not one, a props key holding another kind than asked for. */
final class MalformedValue extends \InvalidArgumentException
{
  public static function named(string $what, string $value): self
  {
    return new self(sprintf('not a %s: %s', $what, $value));
  }

  public static function notA(string $kind, string $key): self
  {
    return new self(sprintf('%s does not hold %s', $key, $kind));
  }
}
