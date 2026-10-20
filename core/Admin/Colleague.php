<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;

/* One row of the Accounts card, as `Colleagues` put it for the account reading it:
   the person's name and picture, the role and scopes as one sentence, the facts
   beneath, and whether it is the last owner, whom the dialog offers no way down. */
final readonly class Colleague
{
  public function __construct(
    public Account $account,
    public string $name,
    public string $sentence,
    public string $facts,
    public ?string $picture,
    public bool $lastOwner,
  ) {}
}
