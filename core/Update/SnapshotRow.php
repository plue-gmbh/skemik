<?php

declare(strict_types=1);

namespace Update;

/* A snapshot as the backups screen lists it: when, in the house's time and the
   reader's words, what its files take, and whether that is too much to download
   through the browser. */
final readonly class SnapshotRow
{
  public function __construct(
    public Snapshot $snapshot,
    public string $when,
    public string $size,
    public bool $large,
  ) {}
}
