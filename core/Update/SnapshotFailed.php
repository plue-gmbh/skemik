<?php

declare(strict_types=1);

namespace Update;

/* A snapshot could not be written, or could not be put back. */
final class SnapshotFailed extends \RuntimeException
{
  public static function writing(string $name, \Throwable $cause): self
  {
    return new self(sprintf('%s was not written: %s', $name, $cause->getMessage()), 0, $cause);
  }

  public static function incomplete(string $name): self
  {
    return new self(sprintf('%s holds no database or no media tree', $name));
  }

  public static function restoring(string $what): self
  {
    return new self('the restore could not ' . $what);
  }
}
