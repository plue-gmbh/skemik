<?php

declare(strict_types=1);

namespace Storage;

use Domain\Violation;

/* What the last holdings check found, when, and under which version of the
   profile, which the fields it asked for belong to. Kept as a setting, since a
   sampled pass answers over many requests and the screens read it between them. */
final readonly class HoldingsReport
{
  /** @param list<Violation> $violations */
  public function __construct(
    public array $violations,
    public \DateTimeImmutable $at,
    public string $version,
  ) {}
}
