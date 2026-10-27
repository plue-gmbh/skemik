<?php

declare(strict_types=1);

namespace Storage;

use Domain\Exceptions\MalformedValue;
use Profile\FieldDefinition;
use Profile\FieldKind;

/* A filter on a number, a date or a time asks a range, `from..to`, one end left
   empty for open, and a bare value is both ends. The clause and the bounds it
   reads stand together, since each is written for the other. */
final readonly class Range
{
  public const KINDS = [FieldKind::Number, FieldKind::Date, FieldKind::DateTime];
  private const SHAPES = ['date' => '/^\d{4}(-\d{2}){0,2}$/',
    'date_time' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'];

  public static function clause(FieldDefinition $field, int $index): string
  {
    $each = $field->repeatable ? "json_each(e.props, :k$index) x, " : '';
    $value = $field->repeatable ? 'x.value' : "json_extract(e.props, :k$index)";

    return "exists (select 1 from {$each}json_each(:f$index) v where "
      . self::end($field, $value, 0, '>=') . ' and ' . self::end($field, $value, 1, '<=') . ')';
  }

  /* One end of a range, open where the bound is null. A date compares cut to
     the bound's length, so `2026` holds every day of that year. */
  private static function end(FieldDefinition $field, string $value, int $end, string $operator): string
  {
    $bound = "json_extract(v.value, '$[$end]')";
    $compared = $field->kind === FieldKind::Number ? $value : "substr($value, 1, length($bound))";

    return "($bound is null or $compared $operator $bound)";
  }

  /* No end at all asks nothing, so it is refused rather than matching every row.
     @return list<string|int|float|null> */
  public static function bounds(FieldDefinition $field, string $value, string $named): array
  {
    $ends = str_contains($value, '..') ? explode('..', $value, 2) : [$value, $value];
    if ($ends === ['', '']) {
      throw MalformedValue::named('bound for ' . $named, $value);
    }

    return array_map(fn (string $end): string|int|float|null => $end === '' ? null : match (true) {
      $field->kind === FieldKind::Number => is_numeric($end) && is_finite($end + 0) ? $end + 0
        : throw MalformedValue::named('number for ' . $named, $end),
      preg_match(self::SHAPES[$field->kind->value], $end) === 1 => $end,
      default => throw MalformedValue::named($field->kind->value . ' for ' . $named, $end),
    }, $ends);
  }
}
