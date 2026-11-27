<?php

declare(strict_types=1);

namespace Http;

/* A request carrying text that is not UTF-8 or holds a NUL byte
   (`Request::unreadable`): `400`, the part named, before any route is asked. */
final class UnreadableText extends \RuntimeException
{
  public static function in(string $part): self
  {
    return new self(sprintf('%s is not UTF-8 text or holds a NUL byte', $part));
  }
}
