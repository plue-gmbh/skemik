<?php

declare(strict_types=1);

namespace Support;

/* A time that is none, or one the house's timezone skips, carrying what was
   typed so a colleague's screen can word it. */
final class UnreadableTime extends \InvalidArgumentException
{
  private function __construct(string $message, public readonly string $value, public readonly ?string $zone = null)
  {
    parent::__construct($message);
  }

  public static function of(string $value): self
  {
    return new self(sprintf('not a time or date: %s', $value), $value);
  }

  /* The hour the clocks go forward over, which no event can start in. */
  public static function skipped(string $value, string $zone): self
  {
    return new self(sprintf('%s does not exist in %s: the clocks skip that hour', $value, $zone), $value, $zone);
  }
}
