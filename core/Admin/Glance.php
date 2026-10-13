<?php

declare(strict_types=1);

namespace Admin;

use Profile\FieldKind;
use Profile\TypeDefinition;

/* What a list row, a grid tile or a card shows beside its label, chosen by the
   type and never by a house, so a collection reads alike in every install: the
   first select, the first date and the first reference, then what the feed lets
   out and then the rest, in the collection's order. Never a text of paragraphs or
   a list of objects, which want the width a record screen gives them; a card has
   room for one plain long text, its first line. An asset shows its
   description alone, its alt text where it is a picture, which the library is
   looked through to write. A new record shows what the type requires besides,
   since a save refused for a field with no cell could not be mended. */
final readonly class Glance
{
  private const WIDE = [FieldKind::LongText, FieldKind::Markdown, FieldKind::Objects, FieldKind::Image];

  /** @param list<Column> $columns as this account reads them */
  public function __construct(private array $columns, private TypeDefinition $type) {}

  public function media(): bool
  {
    return $this->type->name === 'MediaObject';
  }

  public function label(): ?Column
  {
    return array_find($this->columns, fn (Column $column): bool => $column->field->key === $this->type->namedBy());
  }

  /* A tile's picture where the record is not one itself: the type's first image. */
  public function image(): ?Column
  {
    return array_find($this->columns, fn (Column $column): bool => $column->field->kind === FieldKind::Image);
  }

  /* The date a row shows first, and the one a timeline is ordered and filed by. */
  public function date(): ?Column
  {
    return array_find($this->columns, fn (Column $column): bool
      => in_array($column->field->kind, [FieldKind::Date, FieldKind::DateTime], true));
  }

  /* A card's lines under its label; an asset's description is already its one field. */
  public function prose(): ?Column
  {
    return $this->media() ? null
      : array_find($this->columns, fn (Column $column): bool => $column->field->kind === FieldKind::LongText);
  }

  /** @return list<Column> in the collection's order */
  public function shown(int $count, bool $new): array
  {
    $picked = $this->media() ? array_filter($this->columns, fn (Column $column): bool
      => $column->field->key === 'description') : array_slice($this->chosen(), 0, $count);
    $required = fn (Column $column): bool => $new && $column->editable && $column->field->required
      && $column !== $this->label();

    return array_values(array_filter($this->columns, fn (Column $column): bool
      => in_array($column, $picked, true) || $required($column)));
  }

  /** @return list<Column> */
  private function chosen(): array
  {
    $narrow = array_filter($this->columns, fn (Column $column): bool
      => !in_array($column->field->kind, self::WIDE, true) && $column->field->key !== $this->type->namedBy());
    $first = fn (FieldKind ...$kinds): array => array_slice(array_values(array_filter($narrow,
      fn (Column $column): bool => in_array($column->field->kind, $kinds, true))), 0, 1);
    $date = $this->date();
    $chosen = [...$this->amountFor($first(FieldKind::Select), $narrow), ...($date === null ? [] : [$date]),
      ...$first(FieldKind::Ref, FieldKind::Refs)];
    $rest = array_filter($narrow, fn (Column $column): bool => !in_array($column, $chosen, true));
    usort($rest, fn (Column $one, Column $other): int => $other->feed <=> $one->feed);

    return [...$chosen, ...$rest];
  }

  /* A select on the world's list that is the unit of an amount, a currency, gives
     its place to the amount: a price list saying *Euro* and never how much says nothing. */
  /**
   * @param list<Column> $select
   * @param array<Column> $narrow
   * @return list<Column>
   */
  private function amountFor(array $select, array $narrow): array
  {
    $unit = ($select[0] ?? null)?->field;
    if ($unit?->list === null || $unit->valueObject === null) {
      return $select;
    }
    $amount = array_find($narrow, fn (Column $column): bool => $column->field->kind === FieldKind::Number
      && $column->field->valueObject === $unit->valueObject);

    return $amount === null ? $select : [$amount];
  }
}
