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
  public static function because(string $reason): self
  {
    return new self('the mail relay failed: ' . $reason);
  }
}
