<?php

declare(strict_types=1);

namespace Admin;

/* One parameter narrowing the collection, named in words beside the query bar, and the address
   without it: Needs a look links filters the bar's select cannot spell, and one nobody can see is
   one nobody can take away. */
final readonly class Narrowed
{
  public function __construct(public string $word, public string $without) {}
}
