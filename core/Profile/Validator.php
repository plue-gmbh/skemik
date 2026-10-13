<?php

declare(strict_types=1);

namespace Profile;

use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Relation;
use Domain\Violation;
use Support\Uuid;

/* Returns what failed and never a bool, and never throws for invalid content —
   that is an answer to give a person, not an exceptional condition.
   There is no draft exemption: a record missing a required field cannot be
   saved, which is what makes † worth having and why a mark is placed strictly:
   every mark is a wall. The one row that fails its own
   definition arrives the other way, from a type gaining a † after the fact,
   and it is Storage\HoldingsCheck's to report.
   A record is its `props` and its outbound edges, and both are checked in one
   call, because a † on an edge is as much a wall as one on a scalar and a caller
   holding only half the record would pass half the rules. A `PATCH` carries only
   what changed, so the caller merges it with the stored record before asking. */
final readonly class Validator
{
  /* E.164, and the message names the writing, since it is what a person reads. */
  private const DIALLED = '/^\+[1-9][0-9]{6,14}$/';
  private const NOT_DIALLED = 'not a number to dial, written like +4930123123';

  public function __construct(
    private Profile $profile,
    private EdgeValidator $edges,
    private Kinds $kinds = new Kinds(),
  ) {}

  /**
   * Anything outside the type's list is allowed and untouched: schema.org is
   * open, and a field this version does not know must survive a round trip.
   *
   * @param array<string, mixed> $props the JSON document, as stored
   * @param list<Relation> $edges every outbound edge of the record, as stored
   * @return list<Violation>
   */
  public function check(TypeDefinition $type, array $props, array $edges): array
  {
    $violations = [];
    foreach ($type->fields() as $field) {
      if ($field->writtenBy->isStored() && !$field->cardinality->isEdge()) {
        $violations = [...$violations, ...$this->checkField($field, $props)];
      }
    }

    return [
      ...$violations,
      ...$this->checkLocalised($type, $props[LocalisedText::KEY] ?? []),
      ...$this->edges->check($type, $props, $edges),
    ];
  }

  /* Checked here and not only in `Domain\LocalisedText`, which an ingested
     payload never passes through. @return list<Violation> */
  private function checkLocalised(TypeDefinition $type, mixed $translations): array
  {
    if ($translations === []) {
      return [];
    }
    if (!$type->locale->localises() || !is_array($translations) || array_is_list($translations)) {
      return [new Violation(LocalisedText::KEY, 'not a map of locales on a type that localises')];
    }
    $check = fn (int|string $tag, mixed $words): array => $this->checkLocale($type, (string) $tag, $words);

    return array_merge(...array_map($check, array_keys($translations), $translations));
  }

  /** @return list<Violation> */
  private function checkLocale(TypeDefinition $type, string $tag, mixed $words): array
  {
    $where = LocalisedText::KEY . '.' . $tag;
    if (!$this->isLocale($tag) || !is_array($words) || !$this->isMap($words)) {
      return [new Violation($where, 'not a locale holding words')];
    }
    $violations = [];
    foreach ($words as $key => $value) {
      $field = $type->field((string) $key);
      if (!$field?->holdsWords()) {
        $violations[] = new Violation($where, sprintf('%s is not translatable', $key));
        continue;
      }
      $fallsBack = in_array($value, [null, '', []], true);   // empty reads the authored value
      $found = $fallsBack ? [] : $this->checkField($field, $words);
      foreach ($found as $violation) {
        $violations[] = new Violation($where . '.' . $violation->field, $violation->reason);
      }
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkField(FieldDefinition $field, array $props): array
  {
    $present = array_key_exists($field->key, $props) && $props[$field->key] !== null
      && $props[$field->key] !== '' && $props[$field->key] !== [];
    if (!$present) {
      return $field->required ? [new Violation($field->key, Violation::REQUIRED)] : [];
    }
    $value = $props[$field->key];
    if ($field->repeatable && (!is_array($value) || $this->isMap($value))) {
      $what = $field->kind === FieldKind::Objects ? 'not a list of value objects' : 'not a list';

      return [new Violation($field->key, $what)];   // every reader reads a list
    }
    if ($field->repeatable) {
      return $this->checkEach($field, $value);
    }

    return $this->checkValue($field, $value);
  }

  /** @return list<Violation> */
  private function checkEach(FieldDefinition $field, array $values): array
  {
    $violations = [];
    foreach ($values as $value) {
      $violations = [...$violations, ...$this->checkValue($field, $value)];
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkValue(FieldDefinition $field, mixed $value): array
  {
    if ($field->kind === FieldKind::Objects) {
      return $this->checkValueObject($field, $value);
    }
    if ($field->kind === FieldKind::Select && !in_array($value, $field->choices(), true)) {
      return [new Violation($field->key, 'not one of this field\'s values')];
    }
    if ($this->profile->referring($field) !== null && $field->valueObject !== null) {
      return $this->refers($value) ? [] : [new Violation($field->key, 'not a word or a record')];
    }
    if ($this->schemed($field->key, $value)) {
      return [new Violation($field->key, 'an email address is a bare address, without mailto:')];
    }
    if (!$this->kinds->fits($field->kind, $value)) {
      return [new Violation($field->key, 'not a ' . $field->kind->value)];
    }
    if ($field->dialled && preg_match(self::DIALLED, $value) !== 1) {
      return [new Violation($field->key, self::NOT_DIALLED)];
    }

    return [];
  }

  /* One entry of a repeatable value object: its keys are the object's own and its
     bearing keys are what make it a value at all — an opening-hours row with no
     day is not a row somebody left half-filled, it is not an entry. Which keys
     bear is a statement about the value object, so it is read from the object and never
     from the type that happens to use one. A row naming a record by `@id` is
     somebody who read a value object's name as a type's, and is told the shape
     once rather than that `@id` is no key.
     @return list<Violation> */
  private function checkValueObject(FieldDefinition $field, mixed $value): array
  {
    if (!is_array($value) || !$this->isMap($value)) {
      return [new Violation($field->key, 'not a value object')];
    }
    $object = $this->profile->valueObject((string) $field->ref)
      ?? throw MalformedValue::named('value object the profile declares', (string) $field->ref);
    if (isset($value['@id'])) {
      return [new Violation($field->key, sprintf('%s is written inline, a row of %s, never a record named by @id',
        $object->name, implode(', ', $object->keys)))];
    }
    $violations = [];
    foreach ($value as $key => $held) {
      $reason = $this->keyFails($object, (string) $key, $held);
      if ($reason !== null) {
        $violations[] = new Violation($field->key, $reason);
      }
    }

    return [...$violations, ...$this->unborne($field, $object, $value)];
  }

  /** @param array<string, mixed> $value
      @return list<Violation> */
  private function unborne(FieldDefinition $field, ValueObject $object, array $value): array
  {
    $violations = [];
    foreach ($object->bearing as $key) {
      if (!isset($value[$key]) || $value[$key] === '') {
        $violations[] = new Violation($field->key, sprintf('%s needs %s', $this->named($object), $key));
      }
    }

    return $violations;
  }

  /* The article by the name's sound: the one U here is *Unit*, said with a consonant. */
  private function named(ValueObject $object): string
  {
    return (preg_match('/^[AEIO]/', $object->name) === 1 ? 'an ' : 'a ') . $object->name;
  }

  private function keyFails(ValueObject $object, string $key, mixed $held): ?string
  {
    if (!$object->has($key)) {
      return sprintf('%s has no key %s', $object->name, $key);
    }
    if ($key === $object->reference && !$this->refers($held)) {
      return sprintf('%s is a word or a record', $key);
    }

    return in_array($held, [null, ''], true) ? null : $this->heldFails($object, $key, $held);
  }

  /* A row's value is read as the same value in a field would be. */
  private function heldFails(ValueObject $object, string $key, mixed $held): ?string
  {
    if ($this->schemed($key, $held)) {
      return $key . ' is a bare address, without mailto:';
    }
    if (in_array($key, $object->dialled, true)) {
      return is_string($held) && preg_match(self::DIALLED, $held) === 1 ? null : $key . ' is ' . self::NOT_DIALLED;
    }
    $kind = $object->kinds[$key] ?? null;

    return $kind === null || $this->kinds->fits($kind, $held) ? null : sprintf('%s is not a %s', $key, $kind->value);
  }

  private function schemed(string $key, mixed $value): bool
  {
    return $key === 'email' && is_string($value) && preg_match(Kinds::MAILTO, $value) === 1;
  }

  private function refers(mixed $held): bool
  {
    return is_string($held) || (is_array($held) && array_keys($held) === ['@id']
      && is_string($held['@id']) && preg_match(Uuid::SHAPE, $held['@id']) === 1);
  }

  private function isLocale(string $tag): bool
  {
    try {
      return (new Locale($tag))->tag !== '';
    } catch (MalformedValue) {
      return false;
    }
  }

  /* A value object, as against a list of them. */
  private function isMap(array $value): bool
  {
    return $value !== [] && !array_is_list($value);
  }
}
