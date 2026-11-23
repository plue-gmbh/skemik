<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;

/* A live record an edge comes from, read without its fields (`RelationRepository::pointing`): what a
   record's screen needs to decide whether the reader may see it, and which work it belongs to. */
final readonly class PointingRecord
{
  public function __construct(
    public EntityId $id,
    public string $type,
    public string $root,
    public bool $public,
  ) {}
}
