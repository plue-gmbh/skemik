<?php

declare(strict_types=1);

namespace Support;

final class WordsFailed extends \LogicException
{
  public static function malformed(string $path): self
  {
    return new self(sprintf('%s does not return a map of keys to strings', $path));
  }
}
