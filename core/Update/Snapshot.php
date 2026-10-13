<?php

declare(strict_types=1);

namespace Update;

/* One folder under `backups/`, read off its name: when it was taken, in UTC,
   and what for. */
final readonly class Snapshot
{
  public function __construct(
    public string $name,
    public Occasion $occasion,
    public \DateTimeImmutable $at,
    public string $path,
  ) {}
}
