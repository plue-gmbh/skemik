<?php

declare(strict_types=1);

namespace Support;

final readonly class Fetched
{
  public function __construct(public int $status, public string $body) {}
}
