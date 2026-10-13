<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Locale;
use Http\Request;
use Profile\ClosedList;
use Support\Words;

/* The first run's one form: who owns the house, where they
   log in, the house's clock and the chrome's language. The login address is
   the account's, never copied into the person's `email`, which is content.
   The chrome's language is also the first the house publishes in, so a house
   set up in English does not write its first record as German: in the region
   the installing browser names for it, `de-AT`, else in the one the language is
   most at home in, `de-DE` and `en-US`. The languages card changes it later. */
final readonly class InstallForm
{
  public function __construct(
    public string $name,
    public string $email,
    public \DateTimeZone $timezone,
    public Locale $language,
    public Locale $publishing,
  ) {}

  public static function from(Request $request, Words $words): self
  {
    $name = trim((string) $request->form('name'));
    $email = Account::address((string) $request->form('email'));
    $zone = (string) $request->form('timezone');
    $language = (string) $request->form('language');

    return match (true) {
      $name === '' => throw InstallRefused::because($words->get('install.refused.name')),
      filter_var($email, FILTER_VALIDATE_EMAIL) === false
        => throw InstallRefused::because($words->get('install.refused.email')),
      !in_array($zone, \DateTimeZone::listIdentifiers(), true)
        => throw InstallRefused::because($words->get('install.refused.timezone')),
      !in_array($language, $words->shipped(), true)
        => throw InstallRefused::because($words->get('install.refused.language')),
      default => new self($name, $email, new \DateTimeZone($zone), new Locale($language),
        self::publishing($language, (string) $request->header('Accept-Language'))),
    };
  }

  private static function publishing(string $language, string $accepted): Locale
  {
    foreach (explode(',', $accepted) as $range) {
      $tag = trim(explode(';', $range)[0]);
      $region = preg_match('/^' . $language . '-([a-z]{2})$/i', $tag, $named) === 1 ? strtoupper($named[1]) : '';
      if (in_array($region, ClosedList::Country->codes(), true)) {
        return new Locale($language . '-' . $region);
      }
    }

    return new Locale($language . '-' . \Locale::getRegion(\Locale::addLikelySubtags($language)));
  }
}
