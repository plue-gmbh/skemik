<?php

declare(strict_types=1);

namespace Support;

final class FetchFailed extends \RuntimeException
{
  public static function of(string $url, string $reason): self
  {
    return new self(sprintf('%s: %s', $url, $reason));
  }
}
