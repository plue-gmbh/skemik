<?php

declare(strict_types=1);

namespace Admin;

use Domain\Locale;
use Http\Request;
use Support\Words;

/* The general screen's one form, read into what each store gets: the timezone
   and the admin's language for `setting`, and for `config.php` the relay, the
   security contact and the line under the login form, left out where PHP cannot
   write the file. A relay's password is kept where none is typed, but only at the
   same host under the same user, or a host typed in would be sent the password
   the screen never shows. The contact and the link end up in `security.txt` and
   an `href`, so a line break or another scheme is refused, and the line is one
   line, since the login form draws it as one. */
final readonly class GeneralForm
{
  private const LINE = 200;
  private const NO_RELAY = ['host' => '', 'port' => 587, 'user' => '', 'password' => '', 'sender' => ''];

  /** @param ?array<string, string|null|array<string, string|int>> $config the keys `config.php` gets, as written
      @param bool $proving whether the relay changed and has a host, so it is tested before it is kept */
  public function __construct(
    public \DateTimeZone $timezone,
    public Locale $language,
    public ?array $config,
    public bool $proving,
  ) {}

  /** @param ?array<string, mixed> $held what `config.php` holds, null where it is not written */
  public static function from(Request $request, ?array $held, Words $words): self
  {
    $zone = (string) $request->form('timezone');
    $language = (string) $request->form('language');
    $refused = fn (string $key): SettingRefused => SettingRefused::because($words->get('general.refused.' . $key));
    match (true) {
      !in_array($zone, \DateTimeZone::listIdentifiers(), true) => throw $refused('timezone'),
      !in_array($language, $words->shipped(), true) => throw $refused('language'),
      default => null,
    };
    if ($held === null) {
      return new self(new \DateTimeZone($zone), new Locale($language), null, false);
    }
    $relay = self::relay($request, self::heldRelay($held), $refused);

    return new self(new \DateTimeZone($zone), new Locale($language), ['relay' => $relay,
      'security_contact' => self::contact(trim((string) $request->form('contact')), $refused),
      ...self::line($request, $refused)], $relay['host'] !== '' && $relay !== self::heldRelay($held));
  }

  /** @param array<string, mixed> $held
      @return array{host: string, port: int, user: string, password: string, sender: string} */
  public static function heldRelay(array $held): array
  {
    $relay = is_array($held['relay'] ?? null) ? $held['relay'] : [];

    return ['host' => (string) ($relay['host'] ?? ''), 'port' => (int) ($relay['port'] ?? 587),
      'user' => (string) ($relay['user'] ?? ''), 'password' => (string) ($relay['password'] ?? ''),
      'sender' => (string) ($relay['sender'] ?? '')];
  }

  /** @param array{host: string, port: int, user: string, password: string, sender: string} $held
      @param \Closure(string): SettingRefused $refused
      @return array{host: string, port: int, user: string, password: string, sender: string} */
  private static function relay(Request $request, array $held, \Closure $refused): array
  {
    /** @psalm-taint-escape ssrf */
    $host = trim((string) $request->form('host')); /* refused below unless the pattern reads it as a host name: the
      owner's own relay, spoken SMTP to and nothing else, which `config.php` takes typed just the same */
    if ($host === '') {
      return self::NO_RELAY;
    }
    $port = filter_var(trim((string) $request->form('port')), FILTER_VALIDATE_INT,
      ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $user = trim((string) $request->form('user'));
    $sender = trim((string) $request->form('sender'));
    $typed = (string) $request->form('password');
    $password = $typed === '' && $host === $held['host'] && $user === $held['user'] ? $held['password'] : $typed;
    match (true) {
      preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $host) !== 1 => throw $refused('host'),
      $port === false => throw $refused('port'),
      $user === '' => throw $refused('user'),
      filter_var($sender, FILTER_VALIDATE_EMAIL) === false => throw $refused('sender'),
      $password === '' => throw $refused('password'),
      default => null,
    };

    return ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password, 'sender' => $sender];
  }

  /** @param \Closure(string): SettingRefused $refused */
  private static function contact(string $typed, \Closure $refused): ?string
  {
    $link = str_starts_with($typed, 'https://') && filter_var($typed, FILTER_VALIDATE_URL) !== false;

    return match (true) {
      $typed === '' => null,
      $link || filter_var($typed, FILTER_VALIDATE_EMAIL) !== false => $typed,
      default => throw $refused('contact'),
    };
  }

  /** @param \Closure(string): SettingRefused $refused
      @return array{login_note: ?string, login_link: ?string} */
  private static function line(Request $request, \Closure $refused): array
  {
    $note = trim((string) $request->form('note'));
    $link = trim((string) $request->form('link'));
    $linked = preg_match('#^https?://#i', $link) === 1 && filter_var($link, FILTER_VALIDATE_URL) !== false;
    match (true) {
      mb_strlen($note) > self::LINE || preg_match('/\p{Cc}/u', $note) === 1 => throw $refused('note'),
      $link !== '' && !$linked => throw $refused('link'),
      $link !== '' && $note === '' => throw $refused('linkless'),
      default => null,
    };

    return ['login_note' => $note === '' ? null : $note, 'login_link' => $link === '' ? null : $link];
  }
}
