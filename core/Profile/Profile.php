<?php

declare(strict_types=1);

namespace Profile;

use Domain\Exceptions\UnknownType;

/* The thirty types, held once. Immutable after construction.
   Built from `Seed`, compiled into the release — never read back from the
   database. */
final readonly class Profile
{
  public const CEILING = 30;

  /** @var array<string, TypeDefinition> keyed by short name */
  private array $byName;

  /** @var array<string, ValueObject> keyed by name */
  private array $valueObjects;

  /**
   * @param list<TypeDefinition> $types
   * @param list<ValueObject> $valueObjects what a flattened field points back at
   */
  public function __construct(array $types, array $valueObjects = [])
  {
    $byName = [];
    foreach ($types as $type) {
      $byName[$type->name] = $type;
    }
    $this->byName = $byName;

    $objects = [];
    foreach ($valueObjects as $object) {
      $objects[$object->name] = $object;
    }
    $this->valueObjects = $objects;

    assert(count($byName) === count($types), 'a type name is declared twice');
    assert($this->keysAreSnakeCase(), 'a field key is not snake_case');
    assert($this->aggregatesCompose(), 'an AggregateOffer composes over more than one hop');
    assert($this->averagesReadAuthored(), 'an average of averages is wrong');
  }

  /** @return list<TypeDefinition> */
  public function types(): array
  {
    return array_values($this->byName);
  }

  /* The same thirty under another language's field names (`Support\Words::fields`).
     A field's name is ours in every language and no house's to change: a renamed
     field said one word on the screen and another at the address.
     @param array<string, string> $names */
  public function worded(array $names): self
  {
    return $names === [] ? $this : new self(array_map(
      fn (TypeDefinition $type): TypeDefinition => $type->worded($names), $this->types()),
      $this->valueObjects());
  }

  /** @return list<ValueObject> */
  public function valueObjects(): array
  {
    return array_values($this->valueObjects);
  }

  /* Every key a type holds searched text under: the search index's columns, one
     each, so a search can be asked of the fields its reader reads and no others.
     @return list<string> */
  public function searchedKeys(): array
  {
    $fields = array_merge(...array_map(fn (TypeDefinition $type): array => $type->fields(), $this->types()));
    $words = array_filter($fields, fn (FieldDefinition $field): bool =>
      $field->cardinality === Cardinality::S && $field->kind->isSearched());

    return array_values(array_unique(array_map(fn (FieldDefinition $field): string => $field->key, $words)));
  }

  public function valueObject(string $name): ?ValueObject
  {
    return $this->valueObjects[$name] ?? null;
  }

  /* The value object whose referring key a field holds — a record or a word —
     in every row of an `objects` field, or as the one flattened key that is the
     object's reference; null for a field that refers to nothing. */
  public function referring(FieldDefinition $field): ?ValueObject
  {
    $object = $this->valueObject((string) ($field->ref ?? $field->valueObject));
    $refers = $field->ref !== null || $object?->reference === $field->valueObjectKey;

    return $object?->reference !== null && $refers ? $object : null;
  }

  public function has(string $type): bool
  {
    return isset($this->byName[$this->shortName($type)]);
  }

  public function byType(string $type): TypeDefinition
  {
    return $this->byName[$this->shortName($type)] ?? throw UnknownType::named($type);
  }

  /* A type URL or a short name — entity.type stores the first, a table reads
     the second, and both mean one of the thirty. */
  private function shortName(string $type): string
  {
    return str_starts_with($type, 'https://schema.org/')
      ? substr($type, strlen('https://schema.org/'))
      : $type;
  }

  private function keysAreSnakeCase(): bool
  {
    foreach ($this->byName as $type) {
      foreach ($type->fields() as $field) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $field->key) !== 1) {
          return false;
        }
      }
    }

    return true;
  }

  /* The bound on composition: a range over an edge of the type lands on targets
     whose own range reads the offers that name them, so the chain ends there. */
  private function aggregatesCompose(): bool
  {
    foreach ($this->byName as $type) {
      foreach ($type->fields() as $field) {
        $how = $field->derivation;
        if ($how?->function === Derived::Range && !$how->inbound && !$this->endsAt($type, $field)) {
          return false;
        }
      }
    }

    return true;
  }

  /* A derived field may read a target's derived one — min of mins, a count of
     counts — and an average may not: a true one carries `sum` and `count` up the
     chain, so a composed average is declared as those two or not at all. */
  private function averagesReadAuthored(): bool
  {
    foreach ($this->byName as $type) {
      foreach ($type->fields() as $field) {
        if ($field->derivation?->function === Derived::Avg && !$this->readsStored($type, $field)) {
          return false;
        }
      }
    }

    return true;
  }

  private function readsStored(TypeDefinition $type, FieldDefinition $field): bool
  {
    $key = $field->derivation->path[1] ?? '';
    foreach ($this->readFrom($type, $field->derivation) as $target) {
      if (($this->byName[$target] ?? null)?->field($key)?->writtenBy === WrittenBy::Derived) {
        return false;
      }
    }

    return true;
  }

  /* The types a derivation's edge reaches: an inbound edge is carried by the rows
     it reads, so those are the types naming this one through it.
     @return list<string> */
  private function readFrom(TypeDefinition $type, Derivation $how): array
  {
    $edge = $how->path[0] ?? '';
    if (!$how->inbound) {
      return $type->field($edge)?->targets ?? [];
    }

    return array_keys(array_filter($this->byName, fn (TypeDefinition $theirs): bool
      => array_intersect($theirs->field($edge)?->targets ?? [], [$type->name, '*']) !== []));
  }

  private function endsAt(TypeDefinition $type, FieldDefinition $field): bool
  {
    foreach ($type->field((string) $field->derivation?->path[0])?->targets ?? ['*'] as $target) {
      $theirs = ($this->byName[$target] ?? null)?->field($field->key)?->derivation;
      if ($theirs?->function !== Derived::Range || !$theirs->inbound) {
        return false;
      }
    }

    return true;
  }
}
