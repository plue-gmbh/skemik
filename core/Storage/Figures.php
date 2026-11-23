<?php

declare(strict_types=1);

namespace Storage;

use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\WrittenBy;

/* A number field over everything a query narrows to, for a saved question
   (`Question`): its sum, and its value on the record a date names last. A sum
   is asked of the rows and never of a page, so no record is loaded for it. An
   amount is answered per currency, the one its value object holds beside it,
   since two currencies add up to no number; `''` keys a number without one. */
final readonly class Figures
{
  public function __construct(private \PDO $db, private Profile $profile, private EntityRepository $entities) {}

  /* A number each record holds once and stores: a derived one is computed per record and summed nowhere. */
  public static function reads(FieldDefinition $field): bool
  {
    return $field->kind === FieldKind::Number && !$field->repeatable && !$field->cardinality->isEdge()
      && $field->writtenBy !== WrittenBy::Derived;
  }

  /** @return array<string, int|float> by currency, empty where no record the query holds states the number */
  public function sum(Query $query, FieldDefinition $number): array
  {
    $type = $this->profile->byType($query->type);
    $currency = $type->currencyOf($number);
    /* A null path reads null, so a number with no currency is one group under `''`. */
    $statement = $this->db->prepare('select coalesce(json_extract(e.props, :currency), \'\') as unit,'
      . ' sum(json_extract(e.props, :summed)) as total from entity e where ' . $query->where($type)
      . ' group by unit having total is not null order by unit');
    $statement->execute([...$this->entities->bindings($query, $type), 'summed' => '$.' . $number->key,
      'currency' => $currency === null ? null : '$.' . $currency->key]);

    return array_map(fn (int|float|string $sum): int|float => $sum + 0, $statement->fetchAll(\PDO::FETCH_KEY_PAIR));
  }

  /** @return array<string, int|float> the one currency the newest names, empty where the query holds no record
      or its newest states no number */
  public function latest(Query $query, FieldDefinition $date, FieldDefinition $number): array
  {
    $newest = $this->entities->list($query->newestBy($date->key))[0] ?? null;
    $document = $newest?->props->document() ?? [];
    $value = $document[$number->key] ?? null;
    $currency = $this->profile->byType($query->type)->currencyOf($number);
    $code = $currency === null ? '' : $document[$currency->key] ?? '';

    return is_int($value) || is_float($value) ? [is_string($code) ? $code : '' => $value] : [];
  }
}
