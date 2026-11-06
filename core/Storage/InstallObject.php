<?php

declare(strict_types=1);

namespace Storage;

/* The thing a log row acted on where it is no record, kept in `activity.object_id`. */
final readonly class InstallObject
{
  public function __construct(
    public InstallItem $item,
    public string $id,
  ) {}
}
