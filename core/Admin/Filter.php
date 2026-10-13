<?php

declare(strict_types=1);

namespace Admin;

/* One choice in the query bar's filter: the group it is listed under, and the
   address parameter it sets, spelled as the feed spells it. */
final readonly class Filter
{
  public function __construct(
    public string $group,
    public string $parameter,
    public string $value,
    public string $word,
  ) {}

  /* The option's value, which the page turns back into the address. */
  public function pair(): string
  {
    return http_build_query([$this->parameter => $this->value]);
  }
}
