<?php

declare(strict_types=1);

namespace Admin;

/* The records one collection holds of a search, its best first and the rest
   counted, which its own screen under the same `?q=` lists whole. The count is of
   what the search's limit let through, so past it the page says more and no number. */
final readonly class FoundIn
{
  /** @param list<Found> $shown */
  public function __construct(
    public string $name,
    public string $href,
    public array $shown,
    public int $more,
    public bool $capped,
  ) {}
}
