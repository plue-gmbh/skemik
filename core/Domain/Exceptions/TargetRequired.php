<?php

declare(strict_types=1);

namespace Domain\Exceptions;

/* A purge refused: a required edge of these records names the one to purge,
   and each would be left failing its type. It carries the addresses, so the
   refusal says what to edit or delete first. */
final class TargetRequired extends \RuntimeException
{
  /** @param list<string> $records `collection/slug` each */
  private function __construct(public readonly string $record, public readonly array $records)
  {
    parent::__construct(sprintf('%s is required by %s', $record, implode(', ', $records)));
  }

  /** @param list<string> $records */
  public static function by(string $record, array $records): self
  {
    return new self($record, $records);
  }
}
