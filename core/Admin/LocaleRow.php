<?php

declare(strict_types=1);

namespace Admin;

/* One row of the Languages card: the tag, how many records are written in it,
   and why it stays where it cannot be removed, which the card shows before the
   press is made. */
final readonly class LocaleRow
{
  public function __construct(
    public string $tag,
    public string $count,
    public ?string $refusal,
  ) {}
}
