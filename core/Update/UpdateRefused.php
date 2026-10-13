<?php

declare(strict_types=1);

namespace Update;

/* A release could not be installed by itself, and the sentence says why: the
   owner's screen prints it, and the copy by hand stands. Where the cause was the
   machine's, an extension, a write, room, it may be mended under the same
   release, so it is tried again at the next check; what the release itself got
   wrong, its signature, its archive, its move, no mending here changes. */
final class UpdateRefused extends \RuntimeException
{
  public function __construct(string $reason, public readonly bool $again = true)
  {
    parent::__construct($reason);
  }

  public static function because(string $reason): self
  {
    return new self($reason);
  }

  public static function release(string $reason): self
  {
    return new self($reason, false);
  }

  public static function manifest(string $what): self
  {
    return new self('the manifest ' . $what);
  }

  public static function signature(string $what): self
  {
    return new self('the signature ' . $what, false);
  }
}
