<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* The record moved on since the caller read it. Never retried where it is
   thrown: what a lost race means is the caller's to decide. Neither refusal
   names the record's id, which is half of a feed tag: named, it would let a
   channel make a tag without ever reading the record. */
final class StaleWrite extends \RuntimeException
{
  public static function expected(int $version): self
  {
    return new self(sprintf('the record is no longer at version %d', $version));
  }

  public static function otherRecord(): self
  {
    return new self('this address names another record now, not the one read');
  }
}
