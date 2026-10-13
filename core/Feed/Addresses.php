<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Profile\Profile;
use Storage\Collection;
use Storage\EntityRepository;
use Support\Markup;

/* Where a record of this house leaves, for one caller. An `@id` carries the
   slug and a slug is a name, so whether a record may be named at all is asked
   here once: a nested target, a translation's link, a text's link and a derived
   number's rows pass the same gates or none of them does. `Targets` is the way back, from an
   address to its record, and judges nothing. A work an edge names leaves in the
   reader's language where it has a translation the reader may read: an English
   page shows the English screenshot a product points at in German, and the German
   one while the English is a draft, whichever of the two the product names. */
final readonly class Addresses
{
  /** @param list<Collection> $collections the house's */
  public function __construct(
    private Profile $profile,
    private EntityRepository $entities,
    private PublicFilter $filter,
    private Locale $locale,
    private string $origin,
    private array $collections,
  ) {}

  /* The collection holding the type, one per type at a fixed slug. Null where
     this caller may not learn the record is there. */
  public function collectionOf(Entity $record): ?Collection
  {
    $collection = array_find($this->collections,
      fn (Collection $held): bool => $held->type === $record->type);
    $named = $collection !== null && $record->deletedAt === null
      && $this->filter->passes($record, $collection);

    return $named ? $collection : null;
  }

  /* The reader's translation, else the one named, else the authored one, else any:
     the first this caller may read, and the one named where none may. */
  public function inReadersLocale(Entity $record): Entity
  {
    $forks = $this->profile->byType($record->type)->locale->forks();
    $readable = fn (?Entity $one): bool => $one !== null && $this->collectionOf($one) !== null;
    if (!$forks || ($record->locale->tag === $this->locale->tag && $readable($record))) {
      return $record;
    }
    $group = $this->entities->group($record);
    $authored = array_filter($group->members, fn (Entity $member): bool => $member->translationOf === null);

    return array_find([$group->inLocale($this->locale), $record, ...$authored, ...$group->members], $readable)
      ?? $record;
  }

  public function of(Entity $record): ?string
  {
    $collection = $this->collectionOf($record);

    return $collection === null ? null : $this->in($collection, $record);
  }

  /* A text's link to a record leaves as the record's address, and as its words
     alone where this caller may not name the record: an id is no address, and
     one of a draft would say that the draft is there. */
  public function linked(?string $markdown): ?string
  {
    return $markdown === null ? null : Markup::relinked($markdown, fn (string $address): ?string
      => str_starts_with($address, Markup::RECORD) ? $this->named(substr($address, strlen(Markup::RECORD)))
        : $address);
  }

  private function named(string $id): ?string
  {
    try {
      return $this->of($this->inReadersLocale($this->entities->find(new EntityId($id))));
    } catch (EntityNotFound | MalformedValue) {
      return null;
    }
  }

  /* A work is one record per locale and answers on its own; every other type
     answers on every one, so its address is the one it was asked at. */
  public function in(Collection $collection, Entity $record): string
  {
    $everywhere = $this->profile->byType($record->type)->locale->answersOnEveryLocale();
    $locale = $everywhere ? $this->locale : $record->locale;

    return sprintf('%s/feed/%s/%s/%s', $this->origin, $locale->tag, $collection->slug->value,
      $record->slug->value);
  }
}
