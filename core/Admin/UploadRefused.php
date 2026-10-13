<?php

declare(strict_types=1);

namespace Admin;

/* An upload not taken, its message the sentence naming the file and why, already
   in the reader's words: the library shows it as it stands. */
final class UploadRefused extends \RuntimeException
{
  public static function because(string $sentence): self
  {
    return new self($sentence);
  }
}
