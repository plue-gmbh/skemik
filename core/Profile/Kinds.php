<?php

declare(strict_types=1);

namespace Profile;

use Support\Clock;

/* What a value of each kind looks like, asked of a field's value, a value
   object's key and an edge's words alike, so the three never disagree on what a
   date is. Only the shape: which field requires what is the `Validator`'s. */
final readonly class Kinds
{
  /* `email` holds the bare address and a consumer adds the scheme, so there it is one
     spelling; any other link, a button's *write to us*, is `mailto:` and one address. */
  public const MAILTO = '/^mailto:/i';
  /* RFC 3966 as people write it: digits with the usual separators, `+` in front
     for an international number, and an `sms:` body after `?`. */
  private const NUMBER_TO_DIAL = '/^(tel|sms):\+?[0-9][0-9().\-]{1,30}(\?.*)?$/i';
  /* 24:00 is no time of day: midnight closes at 00:00 or 23:59, as schema.org spells it. */
  private const TIME_OF_DAY = '/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/';
  private const ISO_8601_DURATION = '/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?!$)(\d+H)?(\d+M)?(\d+S)?)?$/';

  public function fits(FieldKind $kind, mixed $value): bool
  {
    return match ($kind) {
      /* No bound until a case names one: a negative amount is a refund somewhere.
         JSON's `1e999` decodes to infinity, which no document can say back. */
      FieldKind::Number => is_int($value) || (is_float($value) && is_finite($value)),
      FieldKind::Boolean => is_bool($value),
      FieldKind::Date => is_string($value) && $this->isDate($value),
      FieldKind::DateTime => is_string($value) && $this->isStoredTime(Clock::STORED, $value),
      FieldKind::Duration => is_string($value) && preg_match(self::ISO_8601_DURATION, $value) === 1,
      FieldKind::Time => is_string($value) && preg_match(self::TIME_OF_DAY, $value) === 1,
      FieldKind::Objects => is_array($value),
      FieldKind::Link => is_string($value) && $this->isLink($value),
      default => is_string($value),
    };
  }

  /* A link's one spelling, asked wherever one enters (`Feed\JsonLd::held`): a
     space, which a mail's subject is typed with and no link can hold, is `%20`,
     as a browser would send it, so a button's `mailto:` saves as it was typed. */
  public static function spelled(string $link): string
  {
    return str_replace(' ', '%20', trim($link));
  }

  /* A value object's row, its link keys spelled as a field's link is.
     @param array<array-key, mixed> $row
     @return array<array-key, mixed> */
  public static function spelledRow(?ValueObject $object, array $row): array
  {
    foreach ($object === null ? [] : $object->kinds as $key => $kind) {
      if ($kind === FieldKind::Link && is_string($row[$key] ?? null)) {
        $row[$key] = self::spelled($row[$key]);
      }
    }

    return $row;
  }

  /* An address somebody can follow: a web URL with a host, a path the feed emits
     under its origin, an email address bare or as `mailto:`, or a number to call
     or write to. A bare word is none, and emitted it is a broken fact in every
     consumer; `www.example.de` is refused, never given a scheme. */
  private function isLink(string $value): bool
  {
    if (preg_match('/\s/', $value) === 1) {
      return false;
    }
    if (str_starts_with($value, '/')) {
      return !str_starts_with($value, '//');
    }
    if (preg_match('/^(tel|sms):/i', $value) === 1) {
      return preg_match(self::NUMBER_TO_DIAL, $value) === 1;
    }
    if (preg_match(self::MAILTO, $value) === 1) {
      $address = (string) strtok(substr($value, strlen('mailto:')), '?');
      return filter_var($address, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
    }
    $parts = parse_url($value);
    $web = is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
      && ($parts['host'] ?? '') !== '';

    return $web || filter_var($value, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
  }

  /* A year, a month or a day, as ISO 8601 reduces a date: a founding nobody
     dated to the day is not given one. `1904` sorts before every day of 1904. */
  private function isDate(string $value): bool
  {
    $format = ['Y', 'Y-m', 'Y-m-d'][substr_count($value, '-')] ?? null;

    return $format !== null && $this->isStoredTime($format, $value);
  }

  /* One spelling and a real day, since Storage sorts and filters these as text. */
  private function isStoredTime(string $format, string $value): bool
  {
    $moment = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));

    return $moment !== false && $moment->format($format) === $value;
  }
}
