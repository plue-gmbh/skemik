<?php

declare(strict_types=1);

namespace Storage;

/* One field a collection carries, named by the type's key and called what the
   release calls it (`Profile::worded`). `feed` is `Feed\PublicFilter`'s third gate
   and starts withheld. */
final readonly class CollectionField
{
  public function __construct(
    public string $key,
    public bool $feed = false,
  ) {}
}
