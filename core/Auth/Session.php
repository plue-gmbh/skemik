<?php

declare(strict_types=1);

namespace Auth;

use Support\Clock;

/* A cookie session holding an account id and nothing about the account, loaded
   fresh per request so a demotion or a disable takes effect at once. It is kept
   in `sessions/`, not PHP's temp directory, which shared hosting often lets other
   tenants read, and beside `holdings/`, so a snapshot never carries one. PHP's
   cookie handling is off because only `index.php` sends a header. Two idle days
   and fourteen in all, since a login costs a trip to the mailbox, both ended on
   the server. The store is pinned to files in PHP's own format whatever
   `php.ini` says, since ending an account's other logins reads them there, and a
   store it cannot see would leave a lost laptop logged in. */
final readonly class Session
{
  private const NAME = 'session';
  private const IDLE_SECONDS = 172800;
  private const ABSOLUTE_SECONDS = 1209600;
  private const TIMES = '/^[^;]+;started\|i:(\d+);seen\|i:(\d+);/';

  public function __construct(private string $directory, private bool $https, private Clock $clock) {}

  /* The session the cookie names, where its file exists and it has not run out.
     A new one is only ever begun (`begin`), so no cookie a stranger makes up
     writes a file into `sessions/`. */
  public function resume(?string $cookie): void
  {
    if ($cookie === null || preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $cookie) !== 1
      || !is_file($this->directory . '/sess_' . $cookie)) {
      return;
    }
    session_id($cookie);
    $this->open();
    $now = $this->clock->now()->getTimestamp();
    if ($now - (int) ($_SESSION['seen'] ?? $now) > self::IDLE_SECONDS
      || $now - (int) ($_SESSION['started'] ?? $now) > self::ABSOLUTE_SECONDS) {
      $this->logOut();
      return;
    }
    $_SESSION['started'] ??= $now;
    $_SESSION['seen'] = $now;
  }

  /* A session where none is open: a login asked for past its limits, or the first run handing the house over. */
  public function begin(): void
  {
    if ($this->active()) {
      return;
    }
    $this->open();
    $_SESSION['started'] = $_SESSION['seen'] = $this->clock->now()->getTimestamp();
  }

  public function active(): bool
  {
    return session_status() === PHP_SESSION_ACTIVE;
  }

  private function open(): void
  {
    if (!is_dir($this->directory) || !is_writable($this->directory)) {
      throw UnwritableDirectory::named($this->directory);
    }
    session_start(['save_handler' => 'files', 'serialize_handler' => 'php',
      'save_path' => $this->directory, 'name' => self::NAME, 'use_cookies' => 0, 'use_strict_mode' => 1,
      'cache_limiter' => '', 'gc_maxlifetime' => self::IDLE_SECONDS,
      'gc_probability' => 1, 'gc_divisor' => 100]);
  }

  public function accountId(): ?string
  {
    return isset($_SESSION['account']) ? (string) $_SESSION['account'] : null;
  }

  /* What a login code is bound to: stable across requests until the login, and
     not the session id, so the stored hash of it leads nowhere. */
  public function key(): string
  {
    return (string) ($_SESSION['key'] ??= bin2hex(random_bytes(16)));
  }

  /* `Http\Csrf`'s token, kept here because this is the only reader of `$_SESSION`. */
  public function csrfToken(): string
  {
    return (string) ($_SESSION['csrf'] ??= bin2hex(random_bytes(32)));
  }

  /* A value for the next page only: what an action leaves for the screen it
     redirects to, such as the record a delete can still restore. */
  public function keep(string $name, string $value): void
  {
    $_SESSION['kept'][$name] = $value;
  }

  public function take(string $name): ?string
  {
    $value = $_SESSION['kept'][$name] ?? null;
    unset($_SESSION['kept'][$name]);

    return $value === null ? null : (string) $value;
  }

  /* A viewer's choice that outlasts the page, such as the view a collection was
     last switched to: the session's, so the server draws it right the first time. */
  public function remember(string $name, string $value): void
  {
    $_SESSION['remembered'][$name] = $value;
  }

  public function remembered(string $name): ?string
  {
    $value = $_SESSION['remembered'][$name] ?? null;

    return $value === null ? null : (string) $value;
  }

  public function logIn(string $accountId): void
  {
    session_regenerate_id(true);
    $now = $this->clock->now()->getTimestamp();
    $_SESSION = ['account' => $accountId, 'started' => $now, 'seen' => $now];
  }

  public function logOut(): void
  {
    $_SESSION = [];
    session_destroy();
  }

  /* The account's logins in every other browser, gone as a logout leaves one. */
  public function endOthers(string $accountId): void
  {
    foreach ($this->held($accountId) as $file) {
      unlink($file);
    }
  }

  /* Every login of the account, this browser's with them where it is one. */
  public function endAll(string $accountId): void
  {
    $this->endOthers($accountId);
    if ($this->accountId() === $accountId) {
      $this->logOut();
    }
  }

  /* How many other browsers hold a login of the account that has not run out. */
  public function others(string $accountId): int
  {
    $now = $this->clock->now()->getTimestamp();

    return count(array_filter($this->held($accountId), function (string $file) use ($now): bool {
      $live = preg_match(self::TIMES, (string) file_get_contents($file, false, null, 0, 128), $times) === 1;

      return $live && $now - (int) $times[2] <= self::IDLE_SECONDS
        && $now - (int) $times[1] <= self::ABSOLUTE_SECONDS;
    }));
  }

  /* `logIn` writes the account first, so another login of it is a file that
     begins with it, and an anonymous one never does.
     @return list<string> */
  private function held(string $accountId): array
  {
    $own = $this->active() ? session_id() : '';
    $head = sprintf('account|s:%d:"%s";', strlen($accountId), $accountId);

    return array_values(array_filter(glob($this->directory . '/sess_*') ?: [],
      fn (string $file): bool => basename($file) !== 'sess_' . $own
        && file_get_contents($file, false, null, 0, strlen($head)) === $head));
  }

  /* The `Set-Cookie` value for `Http\Response`; after a logout, the one that clears it. */
  public function cookie(): string
  {
    $active = $this->active();
    $value = sprintf('%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax', self::NAME,
      $active ? session_id() : '', $active ? self::ABSOLUTE_SECONDS : 0);

    return $this->https ? $value . '; Secure' : $value;
  }
}
