<?php

declare(strict_types=1);

namespace Admin;

/* One line of the first run's requirements page, said as the thing it checked. */
final readonly class Requirement
{
  public function __construct(public string $sentence, public bool $met) {}
}
