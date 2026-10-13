<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Session;
use Domain\Entity;

/* The header's account button and its menu: the person's name and first image
   the account may see, the login address, which is the account's and not the
   person's `email`, and how many other browsers a logout everywhere would end.
   The image is drawn as a chip draws it, so one in the trash or outside the
   read scope falls back to the initials rather than a broken picture. */
final readonly class AccountMenu
{
  public string $name;
  public string $email;
  public ?string $picture;
  public int $others;

  public function __construct(Account $account, Entity $person, Rows $rows, Session $session)
  {
    $this->name = (string) $person->props->string('name');
    $this->email = $account->email;
    $this->picture = array_find($rows->chips($account, $person, [])['image'] ?? [],
      fn (Chip $chip): bool => $chip->preview !== null)?->preview;
    $this->others = $session->others($account->id);
  }
}
