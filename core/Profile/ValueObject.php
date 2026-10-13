<?php

declare(strict_types=1);

namespace Profile;

/* One value object: what keys it has, and which of them bear it. It is never a
   field type, but what a flattened field points back at and what the `objects`
   kind draws its columns from. One key may refer to a record or stay the word
   somebody typed, never the record alone, or a one-off item and an outside
   examiner would each need a record first. Such a reference is no edge, so
   nothing reverses, orders or qualifies it and deleting the target leaves it
   behind; a field that cannot pay that is an edge. */
final readonly class ValueObject
{
  /**
   * @param list<string> $keys the schema.org spelling of every key
   * @param list<string> $bearing the keys a † on this value object requires —
   *   never one of two, because the profile holds no rule spanning two fields
   * @param bool $amount an amount object: a use carries its bearing keys
   *   and the unit where a house can vary it, never the range keys nobody fills.
   *   Every other value object gives a use all of its keys.
   * @param ?string $reference the one key that holds a record or a word
   * @param list<string> $targets the types that key may refer to
   * @param list<string> $dialled the keys that hold a number to dial, as `FieldDefinition::dialled`
   * @param array<string, FieldKind> $kinds the keys of a row that are not text, so a
   *   row's `email` is refused where the same value in a field would be. A key
   *   schema.org gives two ranges stays text.
   */
  public function __construct(
    public string $name,
    public array $keys,
    public array $bearing = [],
    public bool $amount = false,
    public ?string $reference = null,
    public array $targets = [],
    public array $dialled = [],
    public array $kinds = [],
  ) {
    assert($keys !== [], 'a value object has keys');
    assert(array_diff($bearing, $keys) === [], 'a bearing key is a key of this value object');
    assert(array_diff($dialled, $keys) === [], 'a dialled key is a key of this value object');
    assert(array_diff(array_keys($kinds), $keys) === [], 'a kind is said of a key of this value object');
    assert($reference === null || in_array($reference, $keys, true), 'the reference is a key');
    assert(($reference === null) === ($targets === []), 'a reference names its targets, and only one does');
  }

  /* The id of the record a row refers to, null where it holds a word or nothing. */
  public function referredBy(mixed $row): ?string
  {
    $held = is_array($row) && $this->reference !== null ? $row[$this->reference] ?? null : null;

    return $this->referenced($held);
  }

  /* The same of the referring key's value alone, as a flattened field holds it. */
  public function referenced(mixed $held): ?string
  {
    $id = is_array($held) ? $held['@id'] ?? null : null;

    return is_string($id) ? $id : null;
  }

  /* What a typed word is emitted as, so a consumer reads `name` either way: the
     one type the key refers to, and a `Thing` where a word cannot say which. */
  public function wordType(): string
  {
    return count($this->targets) === 1 ? $this->targets[0] : 'Thing';
  }

  public function has(string $key): bool
  {
    return in_array($key, $this->keys, true);
  }

  /* A value object with no bearing key cannot be required: there is no test for
     whether it is filled, so a type may not mark it †. */
  public function canBeRequired(): bool
  {
    return $this->bearing !== [];
  }
}
