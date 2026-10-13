<?php

declare(strict_types=1);

namespace Auth;

/* `sessions/`, `login/` or the media tree cannot be written. All halt rather
   than degrade: a session store that fell back to the shared temp directory is
   the leak `Session` exists to prevent, a floor that was not written is an
   operator locked out of their own install, and an upload with no file is
   a record pointing nowhere. */
final class UnwritableDirectory extends \RuntimeException
{
  public static function named(string $directory): self
  {
    return new self(sprintf('%s is not a writable directory', $directory));
  }
}
