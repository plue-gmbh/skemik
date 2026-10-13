<?php

declare(strict_types=1);

namespace Support;

final class JsonFailed extends \RuntimeException
{
  public static function unreadable(string $path): self
  {
    return new self(sprintf('cannot read %s', $path));
  }

  public static function malformed(string $why): self
  {
    return new self(sprintf('not valid JSON: %s', $why));
  }
}
