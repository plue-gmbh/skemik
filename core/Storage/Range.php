<?php

declare(strict_types=1);

namespace Storage;

use Domain\Exceptions\MalformedValue;
use Profile\FieldDefinition;
use Profile\FieldKind;

/* A filter on a number, a date or a time asks a range, `from..to`, one end left
   empty for open, and a bare value is both ends. The clause and the bounds it
   reads stand together, since each is written for the other. A field holding a
   day or a moment is asked twice over: a day by the days asked, a moment by
   their instants, which `Feed\FeedRequest` joins with `BOTH`, knowing the zone.
   An end may be relative, `today`, `today-1`, `month`, which the admin alone reads
   (`dated`): a frontend keeps a document while nothing is written, and a day turning
   writes nothing, so the feed's dates stay absolute. */
final readonly class Range
{
  public const KINDS = [FieldKind::Number, FieldKind::Date, FieldKind::DateTime, FieldKind::DateOrTime];
  public const DATES = [FieldKind::Date, FieldKind::DateTime, FieldKind::DateOrTime];
  public const BOTH = '|';
  private const RELATIVE = '/^(today|month)([+-]\d{1,4})?$/';
  private const SHAPES = ['date' => '/^\d{4}(-\d{2}){0,2}$/',
    'date_time' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'];

  /* A derived value is read once per row, as the one value a repeated field holds many of. */
  public static function clause(FieldDefinition $field, int $index, ?string $computed = null): string
  {
    $each = match (true) {
      $computed !== null => "json_each(json_array($computed)) x, ",
      $field->repeatable => "json_each(e.props, :k$index) x, ",
      default => '',
    };
    $value = $each === '' ? "json_extract(e.props, :k$index)" : 'x.value';
    $within = fn (int $from): string => '(' . self::end($field, $value, $from, '>=') . ' and '
      . self::end($field, $value, $from + 1, '<=') . ')';
    $test = $field->kind === FieldKind::DateOrTime
      ? "case when length($value) <= length('YYYY-MM-DD') then {$within(0)} else {$within(2)} end" : $within(0);

    return "exists (select 1 from {$each}json_each(:f$index) v where $test)";
  }

  /* One end of a range, open where the bound is null. A date compares cut to
     the bound's length, so `2026` holds every day of that year. */
  private static function end(FieldDefinition $field, string $value, int $end, string $operator): string
  {
    $bound = "json_extract(v.value, '$[$end]')";
    $compared = $field->kind === FieldKind::Number ? $value : "substr($value, 1, length($bound))";

    return "($bound is null or $compared $operator $bound)";
  }

  /* A filter's values with each relative end as the day or the month it names, counted from the install's day. */
  public static function dated(string $value, \DateTimeImmutable $today): string
  {
    $absolute = fn (string $end): string => preg_match(self::RELATIVE, $end, $asked) === 1
      ? self::moved($today, $asked[1] === 'month', (int) ($asked[2] ?? 0)) : $end;

    return implode(',', array_map(fn (string $one): string
      => implode('..', array_map($absolute, explode('..', $one, 2))), explode(',', $value)));
  }

  private static function moved(\DateTimeImmutable $today, bool $month, int $by): string
  {
    $from = $month ? $today->modify('first day of this month') : $today;

    return $from->modify(sprintf('%+d %s', $by, $month ? 'month' : 'day'))->format($month ? 'Y-m' : 'Y-m-d');
  }

  /** No end at all asks nothing, so it is refused rather than matching every row.
      @return list<string|int|float|null> */
  public static function bounds(FieldDefinition $field, string $value, string $named): array
  {
    if ($field->kind !== FieldKind::DateOrTime) {
      return self::ends($field->kind, $value, $named);
    }
    $asked = explode(self::BOTH, $value, 2) + [1 => ''];

    return [...self::ends(FieldKind::Date, $asked[0], $named), ...self::ends(FieldKind::DateTime, $asked[1], $named)];
  }

  /** @return list<string|int|float|null> */
  private static function ends(FieldKind $kind, string $value, string $named): array
  {
    $ends = str_contains($value, '..') ? explode('..', $value, 2) : [$value, $value];
    if ($ends === ['', '']) {
      throw MalformedValue::named('bound for ' . $named, $value);
    }

    return array_map(fn (string $end): string|int|float|null => $end === '' ? null : match (true) {
      $kind === FieldKind::Number => is_numeric($end) && is_finite($end + 0) ? $end + 0
        : throw MalformedValue::named('number for ' . $named, $end),
      preg_match(self::SHAPES[$kind->value], $end) === 1 => $end,
      default => throw MalformedValue::named($kind->value . ' for ' . $named, $end),
    }, $ends);
  }
}
