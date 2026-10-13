<?php

declare(strict_types=1);

namespace Auth;

/* A change an account's invariants refuse. Each names what refused
   it, because the operator's next move depends on which: another owner appointed
   first, or the account deleted before its person. */
final class AccountRefused extends \RuntimeException
{
  private function __construct(string $message, public readonly ?Account $held = null)
  {
    parent::__construct($message);
  }

  public static function lastOwner(): self
  {
    return new self('the last owner cannot be demoted, disabled or deleted; appoint another first');
  }

  /* An owner's account is deleted only while another owner remains, so the
     sentence says so rather than sending the operator into the second refusal. */
  public static function holdsPerson(Account $account): self
  {
    $first = $account->role === Role::Owner ? 'appoint another owner and delete it' : 'delete it';

    return new self(sprintf('this person holds the account %s; %s before the person',
      $account->email, $first), $account);
  }
}
