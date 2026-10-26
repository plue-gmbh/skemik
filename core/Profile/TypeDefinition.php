<?php

declare(strict_types=1);

namespace Profile;

/* One of the thirty types as data: what it is called, what it may hold, whether
   a purge of it warns first, how it carries a second language, and whether it
   is ever public. Immutable.
   The three marks are all a type declares about itself — closed enums,
   positional and none defaulted, so a type added without one does not
   compile rather than quietly becoming the safe-looking value.
   Its ancestry is not here: schema.org states it, Vocabulary carries it, and a
   second copy beside the model is one fact in two places. */
final readonly class TypeDefinition
{
  /* The field every type holds its specialisation in. */
  public const KIND = 'additional_type';

  /**
   * @param list<FieldDefinition> $own the type's own properties
   * @param list<FieldDefinition> $inherited the Thing block, plus CreativeWork where drawn on
   * @param list<string> $nameParts the fields the naming field is filled from, in their order
   */
  public function __construct(
    public string $name,
    public Erasure $erasure,
    public LocaleMode $locale,
    public Reach $reach,
    public array $own = [],
    public array $inherited = [],
    public array $nameParts = [],
  ) {
    /* Every type declares exactly one of each mark — a type added
       without one has to fail rather than default quietly. Enum membership plus
       a required parameter is that assertion; there is nothing left to check. */
    assert($this->name !== '', 'a type names itself');
    assert($this->locale->forks() || !array_any([...$this->own, ...$this->inherited],
      fn (FieldDefinition $field): bool => $field->derivation?->function === Derived::Locale),
      'a field derived from the spine locale on a type that never emits it');
    assert(array_all($this->nameParts, fn (string $part): bool => $this->field($part) !== null),
      'a name is filled from fields of this type');
  }

  /* The type in another language's words: `Type.key` where this type calls a
     field something of its own, `key` otherwise, the seed's English where the
     language has no word yet, and a block's heading the same way by its key.
     @param array<string, string> $names */
  public function worded(array $names): self
  {
    $word = fn (?string $key, ?string $seed): ?string => $key === null ? $seed
      : $names[$this->name . '.' . $key] ?? $names[$key] ?? $seed;
    $name = fn (FieldDefinition $field): FieldDefinition => $field->labelled(
      $word($field->key, $field->label), $word($field->blockKey(), $field->block));

    return new self($this->name, $this->erasure, $this->locale, $this->reach, array_map($name, $this->own),
      array_map($name, $this->inherited), $this->nameParts);
  }

  /* A name follows its parts where nobody typed one: blank, or still what the
     parts gave before this save, so a marriage reaches it. A name typed
     otherwise stands, since a stage name has no parts, and nothing fills the
     parts from a name: splitting one is a guess.
     @param array<string, mixed> $before the stored document
     @param array<string, mixed> $after the document as this save leaves it
     @return array<string, mixed> */
  public function named(array $before, array $after): array
  {
    $joined = fn (array $document): string => implode(' ', array_filter(array_map(
      fn (string $part): string => is_string($document[$part] ?? null) ? trim($document[$part]) : '',
      $this->nameParts)));
    $held = is_string($after[$this->namedBy()] ?? null) ? trim($after[$this->namedBy()]) : '';
    $follows = $held === '' || $held === $joined($before);

    return $follows && $joined($after) !== '' ? [...$after, $this->namedBy() => $joined($after)] : $after;
  }

  public function url(): string
  {
    return 'https://schema.org/' . $this->name;
  }

  /** @return list<FieldDefinition> own first, then inherited, each key once */
  public function fields(): array
  {
    $fields = [];
    foreach ([...$this->own, ...$this->inherited] as $field) {
      $fields[$field->key] ??= $field;
    }

    return array_values($fields);
  }

  public function field(string $key): ?FieldDefinition
  {
    foreach ($this->fields() as $field) {
      if ($field->key === $key) {
        return $field;
      }
    }

    return null;
  }

  /** @return list<string> the † keys — what the Validator refuses to save without */
  public function requiredKeys(): array
  {
    $keys = [];
    foreach ($this->fields() as $field) {
      if ($field->required) {
        $keys[] = $field->key;
      }
    }

    return $keys;
  }

  /** @return list<string> the ! keys — what no second record of the type holds again */
  public function uniqueKeys(): array
  {
    return array_values(array_map(fn (FieldDefinition $field): string => $field->key,
      array_filter($this->fields(), fn (FieldDefinition $field): bool => $field->unique !== null)));
  }

  /* A copy holds every value but the ! ones, so a type requiring one, a term
     its code, has none: the copy would lack it. */
  public function copies(): bool
  {
    return array_intersect($this->uniqueKeys(), $this->requiredKeys()) === [];
  }

  /* The naming field the type requires — a course carries a `headline` it
     inherits and is named by its `name`, a job posting by its `title`. A label
     and a new record's slug are both made of it. */
  public function namedBy(): string
  {
    $required = array_find(['headline', 'title'],
      fn (string $key): bool => $this->field($key)?->required === true);

    return $required ?? 'name';
  }

  /* A row is found by what it is called, so the naming field leads and the
     sentence saying what it is follows: the own properties come before the Thing
     block, which would put an offer's name behind its price and every
     description last. A type told apart by its body, a review or a comment,
     starts without its name and keeps its order.
     @return list<FieldDefinition> what a new collection starts with: † and ‡ */
  public function firstScreen(): array
  {
    $shown = array_values(array_filter($this->fields(), fn (FieldDefinition $f): bool => $f->onFirstScreen()));
    $named = array_any($shown, fn (FieldDefinition $f): bool => $f->key === $this->namedBy());
    $rank = fn (FieldDefinition $f): int => match (true) {
      $f->key === $this->namedBy() => 0,
      $named && $f->key === 'description' => 1,
      default => 2,
    };
    usort($shown, fn (FieldDefinition $a, FieldDefinition $b): int => $rank($a) <=> $rank($b));

    return $shown;
  }

  /**
   * What the Fields dialog offers: own fields first, then inherited, minus
   * what the collection already uses. A computed field is offered too: nobody
   * types it, but whether the documents state it is the house's to decide.
   *
   * @param list<string> $taken keys the collection already carries
   * @return list<FieldDefinition>
   */
  public function suggestions(array $taken): array
  {
    $free = fn (FieldDefinition $f): bool => !in_array($f->key, $taken, true);

    return array_values(array_filter($this->fields(), $free));
  }

  /** @return list<FieldDefinition> the edges and terms — Cardinality E or T */
  public function edges(): array
  {
    return array_values(array_filter($this->fields(), fn (FieldDefinition $f): bool => $f->cardinality->isEdge()));
  }

  /* The document without a value under an edge's key, left by a release that
     made the field one: no screen shows it, and kept it refuses every save,
     since the `Validator` reads it as a second home for the fact.
     @param array<string, mixed> $document @return array<string, mixed> */
  public function valued(array $document): array
  {
    $edges = array_map(fn (FieldDefinition $f): string => $f->key, $this->edges());

    return array_diff_key($document, array_flip($edges));
  }
}
