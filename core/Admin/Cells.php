<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\Locale;
use Profile\ClosedList;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Qualifier;
use Profile\Seed\Terms;
use Support\Clock;
use Support\Template;
use Support\Words;

/* One value as `view/partials/cell` draws it, for every view and for the answer
   to a save, so a cell the island just wrote looks like the one the page loaded.
   Numbers and dates are spelled in the chrome language and a date and time is
   read in the house's timezone; a select's word is the record's language,
   never the admin's (`Profile\Seed\Terms`), and so are an edge's words; a
   text and a select's word are marked with it, so a screen reader speaks them so. */
final readonly class Cells
{
  private const DURATION = '/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/';
  private const DURATION_UNITS = ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'];

  public function __construct(
    private Template $template,
    private Words $words,
    private Terms $terms,
    private Clock $clock,
    private Moments $moments,
    private Profile $profile,
  ) {}

  /** @param list<Chip> $chips the field's edges, when it is one */
  public function html(Entity $entity, FieldDefinition $field, array $chips = []): string
  {
    $value = $field->cardinality->isEdge() ? null : ($entity->props->document()[$field->key] ?? null);
    $empty = in_array($value, [null, '', []], true) && $chips === [];

    return $this->template->render('partials/cell', ['words' => $this->words, 'locale' => $entity->locale->tag,
      'kind' => $field->kind, 'many' => $field->holdsMany(), 'value' => $value, 'chips' => $chips, 'empty' => $empty,
      'says' => array_map(fn (Chip $chip): string => $this->says($chip, $entity->locale), $chips),
      'shown' => match (true) {
        $empty => '',
        is_array($value) && $field->kind === FieldKind::Objects => $this->objects($field, $value, $chips),
        is_array($value) && $chips !== [] => '',
        default => $this->shown($entity, $field, $value),
      }]);
  }

  /* What an editable field holds, in the spelling its editor takes: a date and
     time in the house's timezone, since that is what a person types, and a list
     as its entries.
     @return string|int|float|bool|list<mixed>|null */
  public function editable(Entity $entity, FieldDefinition $field): string|int|float|bool|array|null
  {
    $value = $entity->props->document()[$field->key] ?? null;
    if ($field->kind === FieldKind::DateTime && is_string($value)) {
      return $this->clock->render($value);
    }
    if ($field->repeatable) {
      return is_array($value) && array_is_list($value) ? $value : ($value === null ? [] : [$value]);
    }

    return is_array($value) ? null : $value;
  }

  /** @param string|int|float|bool|array<array-key, mixed>|null $value */
  private function shown(Entity $entity, FieldDefinition $field, string|int|float|bool|array|null $value): string
  {
    return match (true) {
      is_array($value) => implode(', ', array_filter($value, is_scalar(...))),
      $field->kind === FieldKind::Number => $this->amount($entity, $field,
        is_int($value) || is_float($value) ? $value : 0),
      $field->kind === FieldKind::Date => $this->moments->day((string) $value),
      $field->kind === FieldKind::DateTime => $this->moments->time((string) $value),
      $field->kind === FieldKind::Duration => $this->duration((string) $value),
      $field->kind === FieldKind::Select => $this->terms->word($field, (string) $value,
        $entity->locale),
      $field->kind === FieldKind::LongText => strtok((string) $value, "\n") ?: '',
      default => is_bool($value) ? '' : (string) $value,
    };
  }

  /* What an edge says of its target as one line, „CTO · 2021–2024“, its
     description last, since it is the longest. */
  private function says(Chip $chip, Locale $in): string
  {
    $said = $chip->edge?->qualifiersIn($in) ?? [];
    $spelt = fn (Qualifier $key): string => $this->spelt($said, $key);
    $span = $spelt(Qualifier::StartDate) . '–' . $spelt(Qualifier::EndDate);
    $parts = [$spelt(Qualifier::RoleName), $spelt(Qualifier::Name), $span === '–' ? '' : $span,
      $spelt(Qualifier::MembershipNumber), $spelt(Qualifier::MembershipPointsEarned), $spelt(Qualifier::Description)];

    return implode(' · ', array_filter($parts, fn (string $part): bool => $part !== ''));
  }

  /** @param array<string, mixed> $said */
  private function spelt(array $said, Qualifier $key): string
  {
    $value = $said[$key->value] ?? null;

    return match (true) {
      is_int($value) || is_float($value) => $this->number($value),
      !is_string($value) => '',
      $key->kind() === FieldKind::Date => $this->moments->day($value),
      default => $value,
    };
  }

  /* An amount is read with its currency, the one its value object holds beside it,
     so a price says *139,00 €* where the list shows it without the currency's column. */
  private function amount(Entity $entity, FieldDefinition $field, int|float $value): string
  {
    $currency = $field->valueObject === null ? null : array_find($this->profile->byType($entity->type)->fields(),
      fn (FieldDefinition $each): bool => $each->list === ClosedList::Currency
        && $each->valueObject === $field->valueObject);
    $code = $currency === null ? null : ($entity->props->document()[$currency->key] ?? null);
    if (!is_string($code) || $code === '') {
      return $this->number($value);
    }
    $formatter = new \NumberFormatter($this->words->language, \NumberFormatter::CURRENCY);
    $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 20);

    return (string) $formatter->formatCurrency((float) $value, $code);
  }

  /* Every digit held, since a price per gram rounded to three places is another price.
     Grouped from five digits on, as ICU's minimum grouping digits would, which intl
     does not reach: a year is 2026 and never 2.026. */
  private function number(int|float $value): string
  {
    $formatter = new \NumberFormatter($this->words->language, \NumberFormatter::DECIMAL);
    $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 20);
    $formatter->setAttribute(\NumberFormatter::GROUPING_USED, (int) (abs($value) >= 10000));

    return (string) $formatter->format($value);
  }

  private function duration(string $iso): string
  {
    if (preg_match(self::DURATION, $iso, $parts) !== 1) {
      return $iso;
    }
    $shown = [];
    foreach (self::DURATION_UNITS as $index => $unit) {
      if (($parts[$index + 1] ?? '') !== '') {
        $shown[] = $this->words->get('duration.' . $unit, ['n' => $parts[$index + 1]]);
      }
    }

    return implode(' ', $shown);
  }

  /* @param array<array-key, mixed> $items
     @param array<int, Chip> $chips the records its rows refer to, by row */
  private function objects(FieldDefinition $field, array $items, array $chips): string
  {
    $bearing = $this->profile->valueObject((string) $field->ref)?->bearing[0] ?? null;
    $first = $items[0][$bearing] ?? null;
    if (is_array($first) && isset($chips[0])) {
      $first = $chips[0]->gone ? $this->words->get('cell.gone') : $chips[0]->label;
    }
    $count = $this->words->get('cell.entries', ['n' => (string) count($items)]);

    return is_scalar($first) ? $count . ' · ' . $first : $count;
  }
}
