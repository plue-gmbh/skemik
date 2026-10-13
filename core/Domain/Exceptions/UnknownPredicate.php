<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* An edge naming a predicate no type declares: the profile is closed, and a
   predicate that exists only in a list of predicates is not one. */
final class UnknownPredicate extends \RuntimeException
{
  public static function named(string $predicate): self
  {
    return new self(sprintf('no such predicate in the profile: %s', $predicate));
  }
}
