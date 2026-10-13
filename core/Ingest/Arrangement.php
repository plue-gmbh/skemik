<?php

declare(strict_types=1);

namespace Ingest;

use Domain\Violation;
use Profile\FieldDefinition;
use Profile\Reach;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\CollectionField;

/* The arrangement a document sends, read into the fields a collection carries
   (`ArrangeController`): each entry of `variableMeasured` the type's own field
   of that name, or the value object arriving on that property, carried whole
   with its keys named under `valueReference`, and each let out where
   `isAccessibleForFree` says so. What no house arranges by hand is a violation
   naming the entry as the document spelt it: a required field left out, a name
   the type does not have, one named twice, a field let out of a type with no
   public state, a value object flagged whole or sent without its keys, since
   the flag is per key as the catalogue shows it and a house may let the town
   out and keep the street. */
final readonly class Arrangement
{
  public function __construct(private SchemaOrg $schema) {}

  /**
   * @param list<mixed> $variables
   * @return array{list<CollectionField>, list<Violation>}
   */
  public function fields(TypeDefinition $type, array $variables): array
  {
    $fields = [];
    $violations = [];
    foreach ($variables as $variable) {
      foreach ($this->entry($type, $variable) as $each) {
        $violations = $each instanceof Violation ? [...$violations, $each] : $violations;
        $fields = $each instanceof CollectionField ? [...$fields, $each] : $fields;
      }
    }

    return [$fields, [...$violations, ...$this->once($type, $fields), ...$this->required($type, $fields)]];
  }

  /* One entry sent: the value object arriving on that property where it names
     keys under `valueReference`, else the type's own field of that name, since
     both may share it: an event's venue and its online address are `location`.
     @return list<CollectionField|Violation> */
  private function entry(TypeDefinition $type, mixed $variable): array
  {
    $name = is_array($variable) && is_string($variable['propertyID'] ?? null) ? $variable['propertyID'] : null;
    if ($name === null) {
      return [new Violation('variableMeasured', 'names each field by its propertyID')];
    }
    $keyed = array_diff(array_keys($this->named($variable['valueReference'] ?? null)), ['']) !== [];
    $own = $keyed ? null : $this->own($type, $name);

    return $own === null ? $this->object($type, $name, $variable)
      : $this->field($type, $own, $variable, $name);
  }

  /* The type's own field of that name, a value object's key never. */
  private function own(TypeDefinition $type, string $name): ?FieldDefinition
  {
    return array_find($type->fields(), fn (FieldDefinition $field): bool => $field->valueObjectOf === null
      && $this->schema->propertyName($field->key) === $name);
  }

  /**
   * @param array<array-key, mixed> $variable
   * @return list<CollectionField|Violation>
   */
  private function field(TypeDefinition $type, FieldDefinition $field, array $variable, string $spelt): array
  {
    $out = $variable['isAccessibleForFree'] ?? false;
    if (!is_bool($out)) {
      return [new Violation($spelt, 'says with true or false whether it is let out')];
    }
    if ($out && $type->reach !== Reach::Public) {
      return [new Violation($spelt, $type->name . ' has no public state, so nothing of it leaves')];
    }

    return [new CollectionField($field->key, $out)];
  }

  /* A value object whole: every key the type gives it, each let out where the
     entry names it so under `valueReference`.
     @param array<array-key, mixed> $variable
     @return list<CollectionField|Violation> */
  private function object(TypeDefinition $type, string $name, array $variable): array
  {
    $keys = array_values(array_filter($type->fields(),
      fn (FieldDefinition $field): bool => $field->valueObjectOf === $name));
    if ($keys === []) {
      return [new Violation($name, 'is no field of ' . $type->name)];
    }
    if (isset($variable['isAccessibleForFree']) || !isset($variable['valueReference'])) {
      return [new Violation($name, 'lets its keys out one by one, each named under valueReference')];
    }
    $named = $this->named($variable['valueReference']);
    $entry = [];
    foreach ($keys as $key) {
      $spelt = (string) $this->schema->propertyName($key->key);
      $entry = [...$entry, ...$this->field($type, $key, (array) ($named[$spelt] ?? []),
        $name . '.' . $spelt)];
      unset($named[$spelt]);
    }

    return [...$entry, ...array_map(fn (string $stray): Violation => new Violation($name . '.' . $stray,
      'is no key of ' . $name), array_keys($named))];
  }

  /** @return array<string, mixed> the keys sent under a value object's entry, by name */
  private function named(mixed $sent): array
  {
    $named = [];
    foreach (is_array($sent) && array_is_list($sent) ? $sent : [$sent] as $each) {
      $named[is_array($each) && is_string($each['propertyID'] ?? null) ? $each['propertyID'] : ''] = $each;
    }

    return $named;
  }

  /**
   * @param list<CollectionField> $fields
   * @return list<Violation>
   */
  private function once(TypeDefinition $type, array $fields): array
  {
    $keys = array_map(fn (CollectionField $field): string => $field->key, $fields);

    return array_map(fn (string $key): Violation => new Violation($this->schema->spelt($type, $key),
      'is named twice'), array_keys(array_filter(array_count_values($keys), fn (int $n): bool => $n > 1)));
  }

  /**
   * @param list<CollectionField> $fields
   * @return list<Violation>
   */
  private function required(TypeDefinition $type, array $fields): array
  {
    $keys = array_map(fn (CollectionField $field): string => $field->key, $fields);

    return array_map(fn (string $key): Violation => new Violation($this->schema->spelt($type, $key),
      Violation::REQUIRED), array_values(array_diff($type->requiredKeys(), $keys)));
  }
}
