<?php

declare(strict_types=1);

namespace Admin;

/* The first run stops, and its message is the one thing to fix, already in the
   reader's words: the page shows it as it stands. */
final class InstallRefused extends \RuntimeException
{
  public static function because(string $sentence): self
  {
    return new self($sentence);
  }
}
