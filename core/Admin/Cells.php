<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\Locale;
use Profile\Derived;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Qualifier;
use Profile\Seed\Terms;
use Profile\ValueObject;
use Support\Clock;
use Support\Template;
use Support\Words;

/* One value as `view/partials/cell` draws it, for every view and for the answer
   to a save, so a cell the island just wrote looks like the one the page loaded.
   Numbers, dates and a select's word are in the chrome language and a date and
   time is read in the install's timezone; a text and an edge's words are the
   record's language, and a text is marked with it, so a screen reader speaks it so. */
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

  /** @param list<Chip> $chips the field's edges, when it is one, linked where the reader does not edit them */
  public function html(Entity $entity, FieldDefinition $field, array $chips = [], bool $linked = true): string
  {
    $value = $field->cardinality->isEdge() && $field->writtenBy->isStored() ? null
      : ($entity->props->document()[$field->key] ?? null);
    $range = $field->derivation?->function === Derived::Range;
    $empty = in_array($value, [null, '', []], true) && $chips === [];
    $rows = is_array($value) && $field->kind === FieldKind::Objects ? $this->objects($entity, $field, $chips) : [];
    $links = $field->kind === FieldKind::Link ? $this->links($value) : $rows;

    return $this->template->render('partials/cell', ['words' => $this->words, 'locale' => $entity->locale->tag,
      'kind' => $range ? FieldKind::Number : $field->kind, 'many' => $field->holdsMany(), 'value' => $value,
      'chips' => $chips, 'empty' => $empty,
      'linked' => $linked, 'links' => !$empty && $linked
        && array_any($links, fn (CellLink $each): bool => $each->href !== null) ? $links : [],
      'says' => array_map(fn (Chip $chip): string => $this->says($chip, $entity->locale), $chips),
      'pairs' => !$empty && array_any($rows, fn (CellLink $row): bool => $row->value !== null) ? $rows : [],
      'shown' => match (true) {
        $empty => '',
        $range && is_array($value) => $this->ranges($value),
        is_array($value) && $field->kind === FieldKind::Objects => $rows === []
          ? $this->words->get('cell.entries', ['n' => (string) count($value)])
          : implode(', ', array_map(fn (CellLink $row): string => $row->word, $rows)),
        is_array($value) && $chips !== [] => '',
        default => $this->shown($entity, $field, $value),
      }]);
  }

  /** What an editable field holds, in the spelling its editor takes: a date and
      time in the install's timezone, since that is what a person types, and a list
      as its entries.
      @return string|int|float|bool|list<mixed>|null */
  public function editable(Entity $entity, FieldDefinition $field): string|int|float|bool|array|null
  {
    $value = $entity->props->document()[$field->key] ?? null;
    if (in_array($field->kind, [FieldKind::DateTime, FieldKind::DateOrTime], true) && is_string($value)
      && str_ends_with($value, 'Z')) {
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
      $field->kind === FieldKind::DateOrTime => str_ends_with((string) $value, 'Z')
        ? $this->moments->time((string) $value) : $this->moments->day((string) $value),
      $field->kind === FieldKind::Duration => $this->duration((string) $value),
      $field->kind === FieldKind::Select => $this->terms->word($field, (string) $value, $this->chrome()),
      $field->kind === FieldKind::LongText => strtok((string) $value, "\n") ?: '',
      default => is_bool($value) ? '' : (string) $value,
    };
  }

  private function chrome(): Locale
  {
    return new Locale($this->words->language);
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
    $currency = $this->profile->byType($entity->type)->currencyOf($field);
    $code = $currency === null ? null : ($entity->props->document()[$currency->key] ?? null);

    return !is_string($code) || $code === '' ? $this->number($value) : $this->money($value, $code);
  }

  private function money(int|float $value, string $code): string
  {
    $formatter = new \NumberFormatter($this->words->language, \NumberFormatter::CURRENCY);
    $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 20);

    return (string) $formatter->formatCurrency((float) $value, $code);
  }

  /** A price range per currency as a card says it, one price where the offers agree: *129,00 € – 149,00 €*.
      @param array<array-key, mixed> $ranges `Feed\Derivations::offerRanges` */
  private function ranges(array $ranges): string
  {
    $said = [];
    foreach (array_filter($ranges, is_array(...)) as $range) {
      $code = (string) ($range['priceCurrency'] ?? '');
      $prices = array_unique([$range['lowPrice'] ?? 0, $range['highPrice'] ?? 0]);
      $said[] = implode(' – ', array_map(fn (mixed $price): string
        => $this->money(is_int($price) || is_float($price) ? $price : 0, $code), $prices));
    }

    return implode(', ', $said);
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

  /** Every row by its bearing key, so rows told apart by their names are told apart in the cell too, each with
      its address or the record it names, and what it says beside its name; no rows when none has a name, since
      the names already say how many there are.
      @param array<int, Chip> $chips the records its rows refer to, by row
      @return list<CellLink> */
  private function objects(Entity $entity, FieldDefinition $field, array $chips): array
  {
    $object = $this->profile->valueObject((string) $field->ref);
    $address = $this->addressKey($object?->kinds ?? []);
    $spoken = fn (array $row, string $key): ?string => is_string($row[$key] ?? null)
      && isset($object?->options[$key]) ? $this->terms->word($field, $row[$key], $this->chrome())
      : $this->spoken($object?->kinds[$key] ?? FieldKind::Text, $row[$key] ?? null);
    $items = $entity->props->document()[$field->key] ?? [];
    $named = [];
    foreach (array_filter(is_array($items) ? $items : [], is_array(...)) as $index => $row) {
      $key = isset($row['name']) ? 'name' : (string) ($object?->bearing[0] ?? '');
      $chip = is_array($row[$key] ?? null) ? $chips[$index] ?? null : null;
      $name = $chip === null ? $spoken($row, $key) : ($chip->gone ? $this->words->get('cell.gone') : $chip->label);
      $link = $address === null ? $chip?->href : $this->href(is_string($row[$address] ?? null) ? $row[$address] : '');
      if ($name !== null && $name !== '') {
        $named[] = new CellLink($name, $link, $object === null ? null : $this->said($object, $row, $spoken),
          $address === null && $chip !== null, isset($object?->options[$key]) ? null : $entity->locale->tag);
      }
    }

    return $named;
  }

  /** What a row says beside its name: the first group of its `said` keys it holds any of, read as a range, and
      its unit after it; null where it holds none.
      @param array<array-key, mixed> $row
      @param \Closure(array<array-key, mixed>, string): ?string $spoken */
  private function said(ValueObject $object, array $row, \Closure $spoken): ?string
  {
    foreach ($object->said as $group) {
      $parts = array_filter(array_map(fn (string $key): ?string => $spoken($row, $key), $group), is_string(...));
      if ($parts !== []) {
        $unit = $spoken($row, 'unitCode');

        return implode('–', array_unique($parts)) . ($unit === null ? '' : ' ' . $unit);
      }
    }

    return null;
  }

  /* One key of a row as a cell reads it, a number in the chrome's spelling and a time to the minute; null
     where it holds nothing a cell can say. */
  private function spoken(FieldKind $kind, mixed $value): ?string
  {
    return match (true) {
      is_int($value) || is_float($value) => $this->number($value),
      !is_string($value) || $value === '' => null,
      $kind === FieldKind::Date => $this->moments->day($value),
      $kind === FieldKind::Time => (string) preg_replace('/^(\d\d:\d\d):00$/', '$1', $value),
      default => $value,
    };
  }

  /** @param string|int|float|bool|array<array-key, mixed>|null $value
      @return list<CellLink> */
  private function links(string|int|float|bool|array|null $value): array
  {
    $held = array_values(array_filter(is_array($value) ? $value : [$value], is_string(...)));

    return array_map(fn (string $link): CellLink => new CellLink($link, $this->href($link)), $held);
  }

  /** The key a row leads to: its `url` where it has one, else its one link, a call to action's target.
      @param array<string, FieldKind> $kinds */
  private function addressKey(array $kinds): ?string
  {
    $links = array_keys(array_filter($kinds, fn (FieldKind $kind): bool => $kind === FieldKind::Link));

    return in_array('url', $links, true) ? 'url' : (count($links) === 1 ? $links[0] : null);
  }

  /* A bare email address is written to, and a path is the install's own, as the feed spells it; `/\` is
     none, since a browser reads it as `//`, another host. */
  private function href(string $link): ?string
  {
    return match (true) {
      preg_match('#^(https?://|/(?![/\\\\])|mailto:|tel:|sms:)#i', $link) === 1 => $link,
      filter_var($link, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false => 'mailto:' . $link,
      default => null,
    };
  }
}
