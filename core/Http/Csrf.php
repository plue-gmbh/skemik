<?php

declare(strict_types=1);

namespace Http;

use Auth\Session;

/* Token per session, required on every state-changing admin request but the
   first run's and a login's ask, before which no session exists
   (`Admin\InstallController`, `Admin\LoginController`). A form
   carries it as `_csrf`, the islands' `fetch` as `X-CSRF-Token`; one token for
   both, since both are this page acting for this session. `SameSite=Lax` alone
   is not the control: it still lets a top-level `POST` from another site through
   in browsers that default it differently. */
final readonly class Csrf
{
  public const FIELD = '_csrf';
  public const HEADER = 'X-CSRF-Token';

  public function __construct(private Session $session) {}

  public function token(): string
  {
    return $this->session->csrfToken();
  }

  public function require(Request $request): void
  {
    $sent = $request->form(self::FIELD) ?? $request->header(self::HEADER) ?? '';
    if (!hash_equals($this->token(), $sent)) {
      throw new CsrfRefused('the request carried no valid CSRF token');
    }
  }
}
