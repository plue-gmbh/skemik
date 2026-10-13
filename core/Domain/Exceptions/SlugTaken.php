<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* A slug another record already holds in the same locale, or in
   any locale where both records answer on every locale route. */
final class SlugTaken extends \RuntimeException
{
  public static function named(string $slug, string $locale): self
  {
    return new self(sprintf('the slug %s is taken in %s', $slug, $locale));
  }
}
