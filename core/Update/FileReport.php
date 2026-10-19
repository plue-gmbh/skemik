<?php

declare(strict_types=1);

namespace Update;

/* What the last comparison of the running tree with its release's `SHA256SUMS`
   found: when it ran and on which release, whether there was a list to compare
   against, and the paths that differ from it, each kind sorted. */
final readonly class FileReport
{
  /**
   * @param list<string> $changed listed, and holding other bytes
   * @param list<string> $missing listed, and not in the tree
   * @param list<string> $added in the tree where a file nobody listed counts
   */
  public function __construct(
    public string $at,
    public string $version,
    public bool $listed,
    public array $changed = [],
    public array $missing = [],
    public array $added = [],
  ) {}

  /** @return list<string> */
  public function differing(): array
  {
    return [...$this->changed, ...$this->missing, ...$this->added];
  }
}
