<?php

declare(strict_types=1);

namespace Support;

final class UnreadableTime extends \InvalidArgumentException
{
  public static function of(string $value): self
  {
    return new self(sprintf('not a time or date: %s', $value));
  }

  /* The hour the clocks go forward over, which no event can start in. */
  public static function skipped(string $value, string $zone): self
  {
    return new self(sprintf('%s does not exist in %s: the clocks skip that hour', $value, $zone));
  }
}
