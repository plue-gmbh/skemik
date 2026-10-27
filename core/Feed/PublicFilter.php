<?php

declare(strict_types=1);

namespace Feed;

use Auth\Grant;
use Auth\TypeScope;
use Domain\Entity;
use Domain\Status;
use Profile\Profile;
use Profile\Reach;
use Storage\Collection;
use Storage\CollectionField;
use Storage\Query;
use Support\Clock;

/* What may leave the house, by three gates: the type (an invoice never can), the
   record's status read against the clock, there being no daemon to flip a row on
   time, and the field's flag. The status is the gate no flag replaces: one
   collection holds public reference customers and customers who must never
   appear, and publishing one is that customer's consent. No gate goes by field
   type, since `email` is an imprint on an `Organization` and a customer's on a
   `Person`. A nested edge re-enters the gates at every depth, and one that fails
   leaves nothing, not an `@id`, whose slug is a name. A stranger, a grant, a
   staff scope, the admin's counts and the export are this one object with the
   gates supplied differently, so no second filter can disagree with it. */
final readonly class PublicFilter
{
  /**
   * @param ?list<Grant> $rules the caller's `read` rules; null is the anonymous reading
   * @param ?TypeScope $scope a staff account's read scope, where a person is asking
   */
  public function __construct(
    private Profile $profile,
    private Clock $clock,
    private ?array $rules = null,
    private ?TypeScope $scope = null,
  ) {
    assert($rules === null || $scope === null, 'a token holds grants and an account a scope');
  }

  /* Read whole, because this caller opens these records on screen: a dump that
     withheld one would disagree with the screen it was taken from. */
  private function inScope(string $typeName): bool
  {
    return $this->scope?->allows(basename($typeName)) === true;
  }

  /* Gate 1: a type that cannot hold `published` lets nothing out. */
  public function passesType(string $typeName): bool
  {
    return $this->profile->byType($typeName)->reach === Reach::Public;
  }

  /* Whether this caller has any reading of the collection at all: without one
     the address answers as absent, never as refused. */
  public function reads(Collection $collection): bool
  {
    return $this->inScope($collection->type) || $this->passesType($collection->type)
      || $this->subjects($collection) !== null;
  }

  /* Gates 1 and 2 on a list: the query narrowed to what this caller may read, or
     null when nothing it asks for could pass. */
  /* A rule bound to a record narrows to it whatever the status gate says: the
     fields it names are that record's, and on every other row they would be read
     past their flags. */
  public function query(Query $query, Collection $collection): ?Query
  {
    if ($this->inScope($collection->type)) {
      return $query;
    }
    $subjects = $this->subjects($collection);
    if ($this->gatedByStatus($collection)) {
      $asked = $query->statuses === [] || in_array(Status::Published, $query->statuses, true);

      return $asked ? $this->narrowed($query, [Status::Published], $subjects ?? []) : null;
    }

    return $subjects === null ? null : $this->narrowed($query, $query->statuses, $subjects);
  }

  /* Gates 1 and 2 on one record — the question the nested-edge rule asks of
     every target at every depth. */
  public function passes(Entity $entity, Collection $collection): bool
  {
    if ($this->inScope($collection->type)) {
      return true;
    }
    $subjects = $this->subjects($collection);
    $bound = in_array($subjects, [null, []], true) || in_array($entity->id->value, $subjects, true);
    if ($this->gatedByStatus($collection)) {
      return $bound && $entity->status->isPublicAt($this->clock->now(), $entity->publishFrom, $entity->publishTo);
    }

    return $subjects !== null && $bound;
  }

  /* Gate 2 is the spine's unless the key was issued to read past it. Every rule
     on the collection has to say so: one that does not was written for the
     published reading, and another's words must not widen it. */
  private function gatedByStatus(Collection $collection): bool
  {
    $rules = $this->rulesFor($collection);
    $past = $rules !== [] && array_all($rules, fn (Grant $rule): bool => $rule->unpublished);

    return $this->passesType($collection->type) && !$past;
  }

  /* Gate 3: the collection's fields whose flag lets them leave, and beside them
     the fields a grant names: a key for a person's email still reads the name a
     stranger reads, or it would know less of a record than nobody does. Every
     rule's fields stand on every record a rule reaches, which holds because a
     key reads a collection one way (`Auth\Grant::readTwoWays`).
     @return list<CollectionField> */
  public function fields(Collection $collection): array
  {
    if ($this->inScope($collection->type)) {
      return $collection->fields;
    }
    $keys = [];
    foreach ($this->rulesFor($collection) as $rule) {
      if ($rule->field === null) {
        return $collection->fields;
      }
      $keys[] = $rule->field;
    }
    $public = $this->passesType($collection->type);

    return array_values(array_filter($collection->fields, fn (CollectionField $field): bool
      => ($public && $field->feed) || in_array($field->key, $keys, true)));
  }

  /**
   * @param list<Status> $statuses
   * @param list<string> $subjects none for the whole collection
   */
  private function narrowed(Query $query, array $statuses, array $subjects): Query
  {
    $filters = $subjects === [] ? $query->filters : [...$query->filters, 'id' => $subjects];

    return new Query($query->type, $filters, $statuses, $query->locale, $query->sort,
      $query->descending, $query->search, $query->page, $query->perPage, $query->searchIn, $query->searchThrough);
  }

  /* What this caller reads of each collection, as the query narrowing to it and
     the fields it reads of it; a collection it has no reading of is left out.
     @param list<Collection> $collections
     @return array<string, Query> by type */
  public function readings(array $collections): array
  {
    $readings = [];
    foreach ($collections as $collection) {
      $keys = array_map(fn (CollectionField $field): string => $field->key, $this->fields($collection));
      $reading = $this->query(new Query($collection->type, searchIn: $keys), $collection);
      if ($reading !== null) {
        $readings[$collection->type] = $reading;
      }
    }

    return $readings;
  }

  /**
   * Gate 2's replacement where the type has no public state: the records a rule
   * binds this reading to.
   *
   * @return ?list<string> empty for the whole collection, null where no rule covers it
   */
  private function subjects(Collection $collection): ?array
  {
    $rules = $this->rulesFor($collection);
    if ($rules === []) {
      return null;
    }
    $ids = [];
    foreach ($rules as $rule) {
      if ($rule->subject === null) {
        return [];
      }
      $ids[] = $rule->subject->value;
    }

    return $ids;
  }

  /** @return list<Grant> */
  private function rulesFor(Collection $collection): array
  {
    return array_values(array_filter($this->rules ?? [],
      fn (Grant $rule): bool => $rule->collection->value === $collection->slug->value));
  }
}
