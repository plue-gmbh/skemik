<?php

declare(strict_types=1);

namespace Admin;

use Auth\Grant;
use Auth\Token;

/* One grant as its badge's popover reviews it: what it may do, and the
   answers an owner looking at the badge needs — is it still in use, who do I
   ask, what else goes if I take it back. Every answer is already a column; this
   only words them. */
final readonly class GrantReview
{
  /** @param list<string> $others the token's other rules, one line per set of collections allowed the same */
  public function __construct(
    public Token $token,
    public Grant $rule,
    public string $sentence,
    public string $issued,
    public string $expires,
    public string $used,
    public string $writes,
    public array $others,
    public ?string $replaced = null,
    public ?string $stale = null,
  ) {}
}
