<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Profile\Derivation;
use Profile\Derived;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\WrittenBy;
use Storage\EntityRepository;
use Storage\RelationRepository;
use Support\Clock;

/* The profile's `∂`, computed at emit and stored nowhere. It is `Feed`'s and not
   the profile's because it needs the request: the reading the caller is
   answering under. Nothing here decides what is computed, `Profile\Derivation`
   does, so a derived field added to a type needs no line here and a line here
   could not invent one. An aggregate runs over rows the caller may already
   fetch, so a consumer walking the edge arrives at the same number, but for the
   one lifecycle gate the declaration names. */
final readonly class Derivations
{
  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private Addresses $addresses,
    private Clock $clock,
  ) {}

  /* Whether this is the writer in force. Almost every derived field says so on
     the type. The rating is the one the holdings answer, record by record and
     with nobody asked: reviews that are rows here are the fact, and where there
     are none the stored number stands. */
  public function derives(Entity $entity, FieldDefinition $field): bool
  {
    if ($field->derivation === null) {
      return false;
    }

    return $field->writerFromHoldings ? $this->targets($entity, $field->derivation) !== []
      : $field->writtenBy === WrittenBy::Derived;
  }

  public function value(Entity $entity, FieldDefinition $field): int|float|null
  {
    $how = $field->derivation;
    if ($how === null) {
      return null;
    }

    return match ($how->function) {
      /* The spine emits `inLanguage` for every record of a forking type, so the
         declaration is the type's table and not a second emitter. */
      Derived::Locale, Derived::Range => null,
      default => $this->aggregate($entity, $how),
    };
  }

  /* A record's `AggregateOffer`s, one per currency, since one spanning two would
     state a number whose unit is a guess. Read inbound they range over the prices
     of the offers that name the record and the gates admit — sold out ones too,
     since leaving them out is a `where` and selection is the address's, and
     none past its `validThrough`; over an edge of the record they merge what each admitted target
     states for its own, one hop and never a chain the request bounds.
     @return list<array<string, mixed>> */
  public function offerRanges(Entity $entity, FieldDefinition $field): array
  {
    return array_values($this->ranges($entity, $field));
  }

  /** @return array<string, array<string, mixed>> by currency code */
  private function ranges(Entity $entity, ?FieldDefinition $field): array
  {
    $how = $field?->derivation;
    if ($how?->function !== Derived::Range) {
      return [];
    }
    $ranges = [];
    foreach ($this->targets($entity, $how) as $target) {
      $found = $how->inbound ? $this->priced($target)
        : $this->ranges($target, $this->profile->byType($target->type)->field($field->key));
      foreach ($found as $currency => $range) {
        $ranges[$currency] = $this->merged($ranges[$currency] ?? null, $range);
      }
    }
    ksort($ranges);

    return $ranges;
  }

  /* An offer with no price or no currency states no range, not a zero, and
     one whose last valid day has passed in the house states none either: *ab
     99 €* for last spring's run is a price nobody can pay.
     @return array<string, array<string, mixed>> */
  private function priced(Entity $offer): array
  {
    $price = $offer->props->document()['price'] ?? null;
    $currency = $offer->props->string('price_currency');
    if (!(is_int($price) || is_float($price)) || $currency === null || $this->lapsed($offer)) {
      return [];
    }

    return [$currency => ['@type' => 'AggregateOffer', 'lowPrice' => $price, 'highPrice' => $price,
      'priceCurrency' => $currency, 'offerCount' => 1]];
  }

  /* A year or a month runs to its end, so the house's today is cut to its length. */
  private function lapsed(Entity $offer): bool
  {
    $through = $offer->props->string('valid_through');
    $today = $this->clock->render($this->clock->now()->format(Clock::STORED));

    return $through !== null && substr($today, 0, strlen($through)) > $through;
  }

  /**
   * @param ?array<string, mixed> $held
   * @param array<string, mixed> $range
   * @return array<string, mixed>
   */
  private function merged(?array $held, array $range): array
  {
    return $held === null ? $range : [...$held, 'lowPrice' => min($held['lowPrice'], $range['lowPrice']),
      'highPrice' => max($held['highPrice'], $range['highPrice']),
      'offerCount' => $held['offerCount'] + $range['offerCount']];
  }

  private function aggregate(Entity $entity, Derivation $how): int|float|null
  {
    $numbers = $this->readings($entity, $how);
    $taken = match ($how->function) {
      Derived::Count => count($numbers),
      Derived::Sum => $numbers === [] ? null : array_sum($numbers),
      Derived::Min => $numbers === [] ? null : min($numbers),
      Derived::Max => $numbers === [] ? null : max($numbers),
      default => $numbers === [] ? null : round(array_sum($numbers) / count($numbers), 2),
    };
    if ($how->minus === null || $taken === null) {
      return $taken;
    }
    $capacity = $entity->props->document()[$how->minus] ?? null;

    return is_int($capacity) ? $capacity - (int) $taken : null;
  }

  /* What the function is taken over: the target key the declaration reads, or
     the targets themselves where it counts them. A target whose key holds no
     number is not a zero — it is a row that says nothing, and averaging a zero
     into a rating would be this class inventing an opinion.
     @return list<int|float> */
  private function readings(Entity $entity, Derivation $how): array
  {
    $key = $how->reads();
    $numbers = [];
    foreach ($this->targets($entity, $how) as $target) {
      $value = $key === null ? 1 : ($target->props->document()[$key] ?? null);
      if (is_int($value) || is_float($value)) {
        $numbers[] = $value;
      }
    }

    return $numbers;
  }

  /** @return list<Entity> the rows in force, through the gates the declaration names */
  private function targets(Entity $entity, Derivation $how): array
  {
    $predicate = (string) $this->schema->propertyName($how->path[0]);
    $edges = $how->inbound
      ? $this->relations->inbound($entity->id)
      : $this->relations->outbound($entity->id);
    $rows = [];
    foreach ($edges as $edge) {
      if ($edge->predicate !== $predicate) {
        continue;
      }
      $target = $this->rowOf($how->inbound ? $edge->subject : $edge->object);
      if ($target !== null && $target->deletedAt === null && $this->admits($target, $how)) {
        $rows[] = $target;
      }
    }

    return $rows;
  }

  private function admits(Entity $target, Derivation $how): bool
  {
    if ($how->lapsedField !== null) {
      return ($target->props->document()[$how->lapsedField] ?? null) !== $how->lapsedValue;
    }

    return $this->addresses->collectionOf($target) !== null;
  }

  private function rowOf(EntityId $id): ?Entity
  {
    try {
      return $this->entities->find($id);
    } catch (EntityNotFound) {
      return null;
    }
  }
}
