<?php

declare(strict_types=1);

namespace Ingest;

use Domain\Exceptions\MalformedValue;
use Feed\Spelling;
use Feed\Targets;
use Profile\FieldDefinition;
use Profile\Kinds;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\ValueObject;

/* A repeated value object's rows as the feed emits them, read back to what is
   stored, and a flattened key that refers the same way. A reference arrives as
   a nested record or a bare `@id`, which name the record, or as a thing of no
   address, which is the word it was typed as; a record of a type the key does
   not refer to is refused here, since only this door and the editor's ever hold
   the record to ask, and one the key does not read as one that does not stand
   (`Edges`). One object where rows are declared is one row, as JSON-LD
   lets a single value stand for a list and as an edge's target is read. A key on
   a fixed list arrives as the token or the member's address and is stored as the
   token, as a select is (`Spelling::token`). */
final readonly class ObjectRows
{
  /** @param \Closure(\Domain\Entity): bool $reads whether the key reads a target */
  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private Targets $targets,
    private \Closure $reads,
  ) {}

  /**
   * @param array<array-key, mixed> $rows
   * @return array<array-key, mixed>
   */
  public function stored(FieldDefinition $field, array $rows): array
  {
    [$object, $kinds] = [$this->profile->referring($field), $this->profile->valueObject((string) $field->ref)];
    $rows = $rows !== [] && !array_is_list($rows) ? [$rows] : $rows;

    return array_map(fn (mixed $row): mixed
      => is_array($row) ? Kinds::spelledRow($kinds, $this->tokened($kinds, $this->row($object, $row))) : $row,
      $rows);
  }

  /** @param array<array-key, mixed> $row @return array<array-key, mixed> */
  private function tokened(?ValueObject $object, array $row): array
  {
    foreach (array_keys($object?->options ?? []) as $key) {
      if (is_string($row[$key] ?? null)) {
        $row[$key] = Spelling::token($row[$key]);
      }
    }

    return $row;
  }

  /* The referring key alone, as a flattened field holds it. */
  public function held(FieldDefinition $field, mixed $raw): mixed
  {
    $object = $this->profile->referring($field);

    $key = (string) $object?->reference;

    return $object === null ? $raw : $this->row($object, [$key => $raw])[$key];
  }

  /**
   * @param array<array-key, mixed> $row
   * @return array<array-key, mixed>
   */
  private function row(?ValueObject $object, array $row): array
  {
    unset($row['@type']);
    $held = $object?->reference === null ? null : $row[$object->reference] ?? null;
    if (is_array($held)) {
      $row[$object->reference] = is_string($held['@id'] ?? null)
        ? ['@id' => $this->referred($object, $held['@id'])] : $held['name'] ?? null;
    }

    return $row;
  }

  private function referred(ValueObject $object, string $address): string
  {
    $record = $this->targets->at($address);
    if ($record === null || !($this->reads)($record)) {
      throw MalformedValue::named('record at', $address);
    }
    if (!$this->schema->takes($object->targets, $record->typeName())) {
      throw MalformedValue::named(implode(' or ', $object->targets) . ' for ' . $object->reference, $address);
    }

    return $record->id->value;
  }
}
