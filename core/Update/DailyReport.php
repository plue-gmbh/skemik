<?php

declare(strict_types=1);

namespace Update;

/* What the last daily run left: the house's day it ran on, why it failed where
   it did, and whether it shrank the window to make room. */
final readonly class DailyReport
{
  public function __construct(public string $day, public ?string $failed, public bool $shrunk) {}
}
