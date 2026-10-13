<?php

declare(strict_types=1);

namespace Auth;

/* Nobody proved anything: no session, a disabled account, or a token unknown,
   revoked or expired. One exception for all of them, because which one applied is
   the thing a caller must not learn. */
final class NotAuthenticated extends \RuntimeException
{
  public static function human(): self
  {
    return new self('no logged-in account');
  }

  public static function machine(): self
  {
    return new self('no valid key');
  }
}
