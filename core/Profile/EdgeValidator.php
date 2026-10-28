<?php

declare(strict_types=1);

namespace Profile;

use Domain\Exceptions\UnknownPredicate;
use Domain\Reason;
use Domain\Relation;
use Domain\Violation;

/* The half of the `Validator` that reads edges, answered from the record's
   outbound edges as they will stand after the write, the same list
   `Storage\RelationRepository::outbound` returns, so the save path and the
   holdings check ask one question. A `props` key naming an edge is refused,
   since two homes for one fact disagree the first time one is edited, and so is
   an edge the type does not declare. A record's kind is read here too, the one
   plain value only the vocabulary can answer for: `ExcerciseAction` is a word no
   frontend filters by. */
final readonly class EdgeValidator
{
  public function __construct(private SchemaOrg $schema, private Kinds $kinds = new Kinds()) {}

  /**
   * @param array<string, mixed> $props
   * @param list<Relation> $edges
   * @return list<Violation>
   */
  public function check(TypeDefinition $type, array $props, array $edges): array
  {
    $violations = [];
    $declared = [];
    foreach ($type->edges() as $field) {
      $predicate = $this->schema->propertyName($field->key) ?? throw UnknownPredicate::named($field->key);
      $declared[] = $predicate;
      $held = array_values(array_filter($edges, fn (Relation $edge): bool => $edge->predicate === $predicate));
      $violations = [...$violations, ...$this->checkField($field, $props, $held)];
    }
    foreach ($edges as $edge) {
      if (!in_array($edge->predicate, $declared, true)) {
        $violations[] = new Violation($edge->predicate, Reason::NoSuchEdge, ['type' => $type->name]);
      }
    }

    return [...$violations, ...$this->checkKind($type, $props[TypeDefinition::KIND] ?? []),
      ...$this->checkOfKind($type, $props, $edges)];
  }

  /* A specialisation's property is held by a record marked as that kind and by
     no other: `variesBy` on a plain product speaks of a group that is none.
     @param array<string, mixed> $props
     @param list<Relation> $edges
     @return list<Violation> */
  private function checkOfKind(TypeDefinition $type, array $props, array $edges): array
  {
    $kinds = (array) ($props[TypeDefinition::KIND] ?? []);
    $predicates = array_map(fn (Relation $edge): string => $edge->predicate, $edges);
    $violations = [];
    foreach ($type->fields() as $field) {
      $held = $field->cardinality->isEdge()
        ? in_array($this->schema->propertyName($field->key), $predicates, true)
        : !in_array($props[$field->key] ?? null, [null, '', []], true);
      if ($field->ofKind !== null && $held && !in_array($field->ofKind, $kinds, true)) {
        $violations[] = new Violation($field->key, Reason::NotMarked, ['kind' => $field->ofKind]);
      }
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkKind(TypeDefinition $type, mixed $kinds): array
  {
    $unknown = array_filter((array) $kinds,
      fn (mixed $kind): bool => !is_string($kind) || !$this->schema->specialises($kind, $type->name));

    return array_map(fn (mixed $kind): Violation => new Violation(TypeDefinition::KIND, Reason::NoSuchKind,
      ['kind' => is_string($kind) ? $kind : 'this', 'type' => $type->name]), array_values($unknown));
  }

  /**
   * @param array<string, mixed> $props
   * @param list<Relation> $held
   * @return list<Violation>
   */
  private function checkField(FieldDefinition $field, array $props, array $held): array
  {
    if (array_key_exists($field->key, $props)) {
      return [new Violation($field->key, Reason::EdgeInProps)];
    }
    if ($held === []) {
      return $field->required ? [new Violation($field->key, Reason::Required)] : [];
    }
    if (count($held) > 1 && !$field->holdsMany()) {
      return [new Violation($field->key, Reason::OneTarget, ['count' => (string) count($held)])];
    }

    return array_merge(...array_map(fn (Relation $edge): array
      => [...$this->checkTarget($field, $edge), ...$this->checkQualifiers($field, $edge)], $held));
  }

  /* An edge's words are read as a field of their kind would be, and what a
     locale holds of its own is words and nothing else.
     @return list<Violation> */
  private function checkQualifiers(FieldDefinition $field, Relation $edge): array
  {
    $violations = [];
    foreach ($edge->qualifiers->document() as $key => $value) {
      $qualifier = Qualifier::tryFrom((string) $key);
      $fits = $qualifier === null ? $this->isWords($value) : $this->kinds->fits($qualifier->kind(), $value);
      if (!$fits) {
        $violations[] = $qualifier === null
          ? new Violation($field->key, Reason::QualifierNotWords, ['key' => (string) $key])
          : new Violation($field->key, Reason::KeyNotAKind,
            ['key' => (string) $key, 'kind' => $qualifier->kind()->value]);
      }
    }

    return $violations;
  }

  private function isWords(mixed $translations): bool
  {
    return is_array($translations) && array_all($translations, fn (mixed $words): bool => is_array($words)
      && array_all($words, fn (mixed $value, int|string $key): bool
        => Qualifier::tryFrom((string) $key)?->holdsWords() === true && is_string($value)));
  }

  /** @return list<Violation> */
  private function checkTarget(FieldDefinition $field, Relation $edge): array
  {
    if (!$this->schema->accepts($field, $edge->objectType)) {
      return [new Violation($field->key, Reason::WrongTarget,
        ['target' => $edge->objectType, 'targets' => implode(' or ', $field->targets)])];
    }

    return [];
  }
}
