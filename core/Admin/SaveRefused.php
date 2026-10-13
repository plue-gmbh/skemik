<?php

declare(strict_types=1);

namespace Admin;

use Domain\Violation;

/* A save the profile refuses, thrown inside its transaction so that what the
   save made on its way, a term a typed word asked for, leaves with it. */
final class SaveRefused extends \RuntimeException
{
  /** @param list<Violation> $violations */
  public function __construct(public readonly array $violations)
  {
    parent::__construct('a save the profile refuses');
  }

  /** @param list<Violation> $violations */
  public static function unless(array $violations): void
  {
    if ($violations !== []) {
      throw new self($violations);
    }
  }
}
