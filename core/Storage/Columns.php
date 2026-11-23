<?php

declare(strict_types=1);

namespace Storage;

/* The generated columns of `entity`, each a value of `props` a save or a screen
   asks of every row of a type, read down an index rather than through
   `json_extract` over every row: a `!` value, which every new record asks, and a
   select field's token, which the query bar asks of every row. A column holds its
   value for the types it names alone, so `identifier` is an invoice's number.
   The table is what the scripts made and not derived from the profile, so a
   release that drops a field or makes it an edge changes no shape. A field the
   table misses is read through `json_extract`, and its column is a script. */
final readonly class Columns
{
  private const TYPES = [
    'order_number' => ['Order'],
    'identifier' => ['Invoice'],
    'term_code' => ['DefinedTerm'],
    'address_country' => ['Person', 'Organization', 'LocalBusiness', 'Place'],
    'currency' => ['HowTo', 'Invoice'],
    'book_format' => ['Book'],
    'in_language' => ['Comment', 'Message', 'Event'],
    'item_condition' => ['Product', 'Offer'],
    'country_of_origin' => ['Product'],
    'has_merchant_return_policy_category' => ['Product', 'Offer'],
    'has_merchant_return_policy_method' => ['Product', 'Offer'],
    'has_merchant_return_policy_fees' => ['Product', 'Offer'],
    'provider_mobility' => ['Service'],
    'price_currency' => ['Offer', 'Reservation'],
    'availability' => ['Offer'],
    'order_status' => ['Order'],
    'discount_currency' => ['Order'],
    'billing_address_country' => ['Order'],
    'payment_status' => ['Invoice'],
    'event_status' => ['Event'],
    'event_attendance_mode' => ['Event'],
    'reservation_status' => ['Reservation'],
    'variable_measured' => ['Observation'],
    'job_location_type' => ['JobPosting'],
    'salary_currency' => ['JobPosting'],
    'application_category' => ['SoftwareApplication'],
    'action_status' => ['Action'],
  ];
  private const VOCABULARY = 'https://schema.org/';

  public static function of(string $key): string
  {
    return 'prop_' . $key;
  }

  /* Whether rows of this type hold the key in its column. */
  public static function holds(string $type, string $key): bool
  {
    return in_array($type, array_map(fn (string $name): string => self::VOCABULARY . $name, self::TYPES[$key] ?? []),
      true);
  }

  /* The key's value on the row `e`, down its column where the type has one. */
  public static function read(string $type, string $key): string
  {
    return self::holds($type, $key) ? 'e.' . self::of($key) : "json_extract(e.props, '$." . $key . "')";
  }

  /** @return list<string> each column as `create table` declares it */
  public static function declared(): array
  {
    $declared = [];
    foreach (self::TYPES as $key => $names) {
      $declared[] = sprintf("%s any generated always as (case when type in ('%s%s') then json_extract(props, '$.%s')"
        . ' end) virtual', self::of($key), self::VOCABULARY, implode("', '" . self::VOCABULARY, $names), $key);
    }

    return $declared;
  }

  /** @return list<string> */
  public static function indexes(): array
  {
    return array_map(fn (string $key): string => sprintf('create index entity_%1$s on entity (type, %1$s,'
      . ' deleted_at) where %1$s is not null', self::of($key)), array_keys(self::TYPES));
  }
}
