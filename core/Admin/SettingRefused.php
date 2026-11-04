<?php

declare(strict_types=1);

namespace Admin;

/* A setting the general screen was sent and keeps nothing of, its message the
   one thing to mend, already in the reader's words. */
final class SettingRefused extends \RuntimeException
{
  public static function because(string $sentence): self
  {
    return new self($sentence);
  }
}
