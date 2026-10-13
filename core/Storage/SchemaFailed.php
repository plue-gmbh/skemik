<?php

declare(strict_types=1);

namespace Storage;

final class SchemaFailed extends \RuntimeException
{
  public static function notEmpty(): self
  {
    return new self('the database is not empty; moving it is the migrations\' work');
  }

  /** @param list<string> $differences */
  public static function differs(array $differences): self
  {
    return new self("the database does not hold the declared shape:\n" . implode("\n", $differences));
  }

  public static function ahead(int $number, int $last): self
  {
    return new self(sprintf('the database was moved to script %d by a later release, and this one ends at %d.',
      $number, $last));
  }

  public static function uncopied(string $path): self
  {
    return new self(sprintf('the copy %s could not be written', $path));
  }

  public static function danglingKeys(string $path): self
  {
    return new self(sprintf('%s leaves a reference to a row that does not exist', $path));
  }

  public static function unreadableScript(string $path): self
  {
    return new self(sprintf('%s does not return a migration', $path));
  }
}
