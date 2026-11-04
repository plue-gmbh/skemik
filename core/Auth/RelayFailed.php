<?php

declare(strict_types=1);

namespace Auth;

/* A relay that could not be reached, spoken to or satisfied. It is thrown once
   the answer has left (`Admin\LoginController`), so it reaches no visitor and is
   a line of the owner's error log: a relay set wrong was codes that never
   arrived and nothing saying why. Never the password, and never what the relay
   answered to the login, which a server may word with what it was sent. */
final class RelayFailed extends \RuntimeException
{
  /* The fault and its detail apart, for the screen that words them; the message is the log's, in English. */
  private function __construct(public readonly RelayFault $fault, public readonly string $detail)
  {
    parent::__construct('the mail relay failed, ' . str_replace('_', ' ', $fault->value)
      . ($detail === '' ? '' : ': ' . $detail));
  }

  public static function because(RelayFault $fault, string $detail = ''): self
  {
    return new self($fault, $detail);
  }
}
