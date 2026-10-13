<?php

declare(strict_types=1);

namespace Ingest;

use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Relation;
use Feed\Targets;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\Predicate;
use Profile\SchemaOrg;

/* An edge field's value read back to its relations, as `Feed\JsonLd` wrote them.
   A nested record is read for its `@id` alone: a write changes one record, and
   the ones it names are named, never written. A target is found by the address
   the feed gave it and must be one this key reads, as a filter's target is
   (`Feed\Targets::filtered`): what the key does not read is refused as what does
   not stand, so a guessed address confirms nothing. A channel naming the draft
   customer it has just made holds `read` past the published for that, as one
   setting a customer does. What is named must already stand, and an address at
   another host than the one written to names nothing here, so a dump posted to
   another domain is refused by each address it carries. A target sent bare says
   no words, and so keeps none. */
final readonly class Edges
{
  /* The key holding the wrapped target; a `Role` repeats its predicate. */
  private const WRAPPERS = ['Role' => '', 'ProgramMembership' => 'program', 'ListItem' => 'item'];

  /** @param \Closure(\Domain\Entity): bool $reads whether the key reads a target */
  public function __construct(
    private SchemaOrg $schema,
    private Predicate $predicates,
    private Targets $targets,
    private \Closure $reads,
  ) {}

  /* A list member is a nested record, a bare `@id` or a term's code — the three
     shapes `JsonLd` emits by depth — or any of them inside the wrapper that
     carried its qualifiers. The list's order is the edges' order.
     @return list<Relation> */
  public function relations(FieldDefinition $field, EntityId $subject, mixed $raw): array
  {
    $members = is_array($raw) && array_is_list($raw) ? $raw : ($raw === null ? [] : [$raw]);
    $predicate = (string) $this->schema->propertyName($field->key);
    $relations = [];
    foreach ($members as $index => $member) {
      [$target, $qualifiers] = $this->unwrapped($predicate, $member);
      $position = $qualifiers['position'] ?? ($field->holdsMany() ? $index + 1 : null);
      if (!is_int($position) && $position !== null) {
        throw MalformedValue::notA('a number', 'position');
      }
      unset($qualifiers['position']);
      [$object, $type, $term] = $this->target($field, $target);
      $relations[] = new Relation($this->predicates, $subject, $predicate, $object, $type, $term, $position,
        new Props($qualifiers));
    }

    return $relations;
  }

  /* Words sent empty are no words, as a field sent empty is no value.
     @return array{mixed, array<string, mixed>} the target, and the qualifiers its wrapper carried */
  private function unwrapped(string $predicate, mixed $member): array
  {
    $wrapper = is_array($member) ? ($member['@type'] ?? null) : null;
    if (!is_string($wrapper) || !isset(self::WRAPPERS[$wrapper])) {
      return [$member, []];
    }
    $key = self::WRAPPERS[$wrapper] === '' ? $predicate : self::WRAPPERS[$wrapper];
    $qualifiers = array_diff_key($member, ['@type' => true, $key => true]);
    if (array_key_exists(LocalisedText::KEY, $qualifiers)) {
      throw MalformedValue::named('word a wrapper carries', LocalisedText::KEY);
    }

    return [$member[$key] ?? null, array_filter($qualifiers, fn (mixed $value): bool
      => !in_array($value, [null, '', []], true))];
  }

  /** @return array{EntityId, string, ?string} the object, its type and its term code */
  private function target(FieldDefinition $field, mixed $target): array
  {
    $address = is_array($target) ? ($target['@id'] ?? null) : $target;
    if (!is_string($address) || $address === '') {
      throw MalformedValue::notA('an @id or a term code', $field->key);
    }
    if ($field->cardinality === Cardinality::T && !is_array($target)) {
      return $this->term($this->schema->termSet($field), $address);
    }

    return $this->addressed($field, $address);
  }

  /* A term named by its address is held to the field's set as one named by its
     code is, or a `keywords` edge would hold a gender that reads back as a code
     the set does not know.
     @return array{EntityId, string, ?string} */
  private function addressed(FieldDefinition $field, string $address): array
  {
    $record = $this->targets->at($address);
    if ($record === null || !($this->reads)($record)) {
      throw MalformedValue::named('record at', $address);
    }
    $set = $this->schema->termSet($field);
    if ($field->cardinality === Cardinality::T && $this->targets->setOf($record) !== $set) {
      throw MalformedValue::named('term in the set ' . $set, $address);
    }
    $term = $record->typeName() === 'DefinedTerm' ? $record->props->string('term_code') : null;

    return [$record->id, $record->typeName(), $term];
  }

  /* A term by its code within the field's set, which the feed emits in place
     of its address; a code the set does not hold is refused, not made, since a
     typo would make a term.
     @return array{EntityId, string, string} */
  private function term(string $set, string $code): array
  {
    $term = $this->targets->term($set, $code);
    if ($term === null || !($this->reads)($term)) {
      throw MalformedValue::named('code of a term in the set ' . $set, $code);
    }

    return [$term->id, 'DefinedTerm', $code];
  }
}
