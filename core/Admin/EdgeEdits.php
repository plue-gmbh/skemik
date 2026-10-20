<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Relation;
use Feed\Targets;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Predicate;
use Profile\Qualifier;
use Profile\SchemaOrg;
use Storage\EntityRepository;

/* What a save sends to a reference, applied to the record's edges: the whole
   ordered list of its targets, replacing that field's edges. `{"edge": n}` keeps
   the nth edge the field held when the page was read, the version the `PATCH`
   names saying which list that was, so an edge's own words survive a reorder.
   `qualifiers` replace what the edge said on the lines the picker drew, and what
   no line draws stays: `potentialAction`, and the other locales' words. What
   else a target carries is the island's drawing and is not read. */
final readonly class EdgeEdits
{
  public function __construct(
    private EntityRepository $entities,
    private Rows $rows,
    private Predicate $predicates,
    private SchemaOrg $schema,
    private TermMaker $terms,
    private Targets $targets,
  ) {}

  /**
   * @param list<Relation> $edges
   * @return list<Relation> the other fields' edges, then this one's as sent
   */
  public function replaced(Account $account, FieldDefinition $field, EntityId $subject, array $edges,
    mixed $sent): array
  {
    $predicate = (string) $this->schema->propertyName($field->key);
    $mine = fn (Relation $edge): bool => $edge->predicate === $predicate;
    $held = array_values(array_filter($edges, $mine));
    $kept = array_values(array_filter($edges, fn (Relation $edge): bool => !$mine($edge)));
    $targets = $sent ?? [];
    if (!is_array($targets) || !array_is_list($targets)) {
      throw MalformedValue::notA('a list of targets', $field->key);
    }
    foreach ($targets as $index => $target) {
      [$object, $type, $term, $qualifiers] = $this->target($account, $field, $held, $target);
      $kept[] = new Relation($this->predicates, $subject, $predicate, $object, $type, $term,
        $field->holdsMany() ? $index + 1 : null, $qualifiers);
    }

    return $kept;
  }

  /* Words sent under another locale: each edge the field holds, by its place,
     saying there what is sent, and no target changed, added or moved. With no
     locale they are the ones it was written with.
     @param list<Relation> $edges every outbound edge, as the save leaves them
     @return list<Relation> */
  public function worded(FieldDefinition $field, ?Locale $locale, array $edges, mixed $sent): array
  {
    $predicate = (string) $this->schema->propertyName($field->key);
    $held = array_keys(array_filter($edges, fn (Relation $edge): bool => $edge->predicate === $predicate));
    if ($field->cardinality !== Cardinality::E || !is_array($sent) || !array_is_list($sent)) {
      throw MalformedValue::notA('a list of the edges it holds', $field->key);
    }
    $lines = array_fill_keys(Qualifier::words(), FieldKind::Text);
    foreach ($sent as $target) {
      $index = is_array($target) && is_int($target['edge'] ?? null) ? $held[$target['edge']] ?? null : null;
      $edge = $index === null ? throw MalformedValue::notA('an edge it holds', $field->key) : $edges[$index];
      $edges[$index] = new Relation($this->predicates, $edge->subject, $edge->predicate, $edge->object,
        $edge->objectType, $edge->objectTerm, $edge->position,
        $this->said($edge->qualifiers, $lines, $locale, $target['qualifiers'] ?? null));
    }

    return $edges;
  }

  /* A record the picker would not have offered is refused as one that does not exist.
     @return array{EntityId, string, ?string, Props} */
  public function record(Account $account, FieldDefinition $field, string $id): array
  {
    try {
      $target = $this->entities->find(new EntityId($id));
    } catch (EntityNotFound | MalformedValue) {
      $target = null;
    }
    if ($target === null || $target->deletedAt !== null || !$this->rows->readable($account, $target)) {
      throw MalformedValue::named('record this account may read, for ' . $field->key, $id);
    }
    $misfit = $this->targets->misfit($field, $target);
    if ($misfit !== null) {
      throw MalformedValue::named($misfit, $id);
    }
    $term = $target->typeName() === 'DefinedTerm' ? $target->props->string('term_code') : null;

    return [$target->id, $target->typeName(), $term, new Props([])];
  }

  /**
   * @param list<Relation> $held
   * @return array{EntityId, string, ?string, Props} the object, its type, its term code, the qualifiers
   */
  private function target(Account $account, FieldDefinition $field, array $held, mixed $target): array
  {
    $sent = is_array($target) ? $target : [];
    $edge = is_int($sent['edge'] ?? null) ? $held[$sent['edge']] ?? null : null;
    [$object, $type, $term, $qualifiers] = match (true) {
      $edge !== null => [$edge->object, $edge->objectType, $edge->objectTerm, $edge->qualifiers],
      is_string($sent['id'] ?? null) => $this->record($account, $field, $sent['id']),
      is_string($sent['word'] ?? null) => $this->termed($field, $sent['word']),
      default => throw MalformedValue::notA('a held edge, an id or a word', $field->key),
    };
    $lines = $field->cardinality === Cardinality::E
      ? Qualifier::lines((string) $this->schema->propertyName($field->key), $type) : [];

    return [$object, $type, $term, $this->said($qualifiers, $lines, null, $sent['qualifiers'] ?? null)];
  }

  /* A word typed into a term field: the term of that word in the field's set,
     made where it is missing (`TermMaker`).
     @return array{EntityId, string, string, Props} */
  private function termed(FieldDefinition $field, string $word): array
  {
    if ($field->cardinality !== Cardinality::T || trim($word) === '') {
      throw MalformedValue::notA('a term field taking a word', $field->key);
    }
    $term = $this->terms->made($this->schema->termSet($field), $field->label, trim($word));

    return [$term->id, 'DefinedTerm', (string) $term->props->string('term_code'), new Props([])];
  }

  /* The lines sent over what the edge held, a blank one saying nothing, in a
     locale's words where one is named.
     @param array<string, FieldKind> $lines */
  private function said(Props $held, array $lines, ?Locale $locale, mixed $sent): Props
  {
    if ($sent === null) {
      return $held;
    }
    if (!is_array($sent) || ($sent !== [] && array_is_list($sent))) {
      throw MalformedValue::notA('an object of what an edge says', 'qualifiers');
    }
    $said = array_filter($sent, fn (mixed $value): bool => !in_array($value, [null, '', []], true));
    foreach (array_keys(array_diff_key($said, $lines)) as $key) {
      throw MalformedValue::named('line the screen writes on this edge', (string) $key);
    }
    $document = $held->document();
    if ($locale === null) {
      return new Props([...array_diff_key($document, $lines), ...$said]);
    }
    $translations = array_filter([...(array) ($document[LocalisedText::KEY] ?? []), $locale->tag => $said]);

    return new Props([...array_diff_key($document, [LocalisedText::KEY => true]),
      ...($translations === [] ? [] : [LocalisedText::KEY => $translations])]);
  }
}
