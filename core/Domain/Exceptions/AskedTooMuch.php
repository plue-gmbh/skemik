<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* A question refused for its size, more words in a search or more values in a
   filter than one question may ask. Its sentence is a machine's, as a malformed
   value's is; what it carries lets a person be told the limit in their words,
   since a search box is where a person meets it. */
final class AskedTooMuch extends \InvalidArgumentException
{
  /** @param ?string $filter the filter's parameter as written, null for a search */
  private function __construct(public readonly ?string $filter, public readonly int $limit, string $message)
  {
    parent::__construct($message);
  }

  public static function words(int $limit): self
  {
    return new self(null, $limit, sprintf('not a search of at most %d words: q', $limit));
  }

  public static function values(string $filter, int $limit): self
  {
    return new self($filter, $limit, sprintf('not a filter of at most %d values: %s', $limit, $filter));
  }
}
