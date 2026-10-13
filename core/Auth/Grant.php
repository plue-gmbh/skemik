<?php

declare(strict_types=1);

namespace Auth;

use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\Slug;

/* One `grant_rule`: a sentence in the admin naming six fields is six of these on
   one token, since a field list or a wildcard on a rule would be a second shape
   of the same answer. The field is a key and no foreign key, so a rule reads as
   the sentence it was written as, and one whose field the collection no longer
   carries is shown as stale. `unpublished` is a `read` rule saying it reads
   what is not published too, the owner's word per rule and said in its
   sentence, because a key is handed to software that is not ours and a draft it
   serves is served. `subject` binds a rule to one record, the one whose screen
   issued it: a subject is the record itself, so *every row one edge away from
   customer X* cannot be said by one rule, and is not meant to be. */
final readonly class Grant
{
  private const FIELD_KEY = '/^[a-z][A-Za-z0-9_]*$/';

  public function __construct(
    public string $id,
    public string $tokenId,
    public Slug $collection,
    public GrantCapability $capability,
    public string $createdAt,
    public ?string $field = null,
    public ?EntityId $subject = null,
    public bool $unpublished = false,
  ) {
    if ($field !== null && preg_match(self::FIELD_KEY, $field) !== 1) {
      throw MalformedValue::named('field key in a grant', $field);
    }
    if ($unpublished && $capability !== GrantCapability::Read) {
      throw MalformedValue::named('capability reading what is not published', $capability->value);
    }
    /* An arrangement is the collection's whole: no field and no record narrows it. */
    if ($capability === GrantCapability::Arrange && ($field !== null || $subject !== null)) {
      throw MalformedValue::named('rule arranging the whole collection', $field ?? (string) $subject?->value);
    }
    /* A record not yet made is nobody's subject, so a bound rule never creates. */
    if ($capability === GrantCapability::Create && $subject !== null) {
      throw MalformedValue::named('rule creating under a subject', $subject->value);
    }
  }

  /* A rule without a field covers every field of the collection, one with a field
     only that field, and a question about the whole collection only the former. */
  public function covers(Slug $collection, ?string $field, GrantCapability $capability): bool
  {
    return $this->on($collection, $capability) && ($this->field === null || $this->field === $field);
  }

  /* Whether the rule says anything about this verb in this collection, whatever field. */
  public function on(Slug $collection, GrantCapability $capability): bool
  {
    return $this->capability === $capability && $this->collection->value === $collection->value;
  }

  /* A rule bound to a subject answers only about that record, and nothing about
     a record not yet made. */
  public function bindsTo(?EntityId $record): bool
  {
    return $this->subject === null || $this->subject->value === $record?->value;
  }

  /* The collection these rules would read two ways, or null. A key reads one
     whole or by some of its records, each with the same fields, since its
     reading is the collection's: `Feed\PublicFilter` reads every rule's fields
     on every record a rule reaches, its filter, sort and `?q=` among them, so a
     second way would widen the first.
     @param list<Grant> $rules */
  public static function readTwoWays(array $rules): ?Slug
  {
    $fields = [];
    foreach ($rules as $rule) {
      if ($rule->capability === GrantCapability::Read) {
        $fields[$rule->collection->value][$rule->subject->value ?? ''][] = $rule->field ?? '';
      }
    }
    foreach ($fields as $collection => $bySubject) {
      $ways = array_unique(array_map(self::fieldSet(...), $bySubject));
      if (count($ways) > 1 || (isset($bySubject['']) && count($bySubject) > 1)) {
        return new Slug((string) $collection);
      }
    }

    return null;
  }

  /** @param list<string> $keys '' for every field */
  private static function fieldSet(array $keys): string
  {
    $keys = array_unique($keys);
    sort($keys);

    return in_array('', $keys, true) ? '' : implode(',', $keys);
  }
}
