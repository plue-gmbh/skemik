<?php

declare(strict_types=1);

namespace Admin;

use Profile\ClosedList;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\TypeDefinition;

/* What a list row, a grid tile or a card shows beside its label: the
   collection's first fields in the order the organisation sets in the table, skipping
   what no record holds, so an empty column fills no view. A value object takes
   one place, shown by its first bearing key, an answer by its text and a price
   by its amount, whose cell carries the currency (`Admin\Cells`), never the
   currency alone. A long text shows its first line; a list of objects wants a
   record screen's width. The type says the label, the date and the picture. An
   asset shows its alt text alone, which the library is looked through to write.
   A new record shows what the type requires besides, since a save refused for a
   field with no cell could not be mended. */
final readonly class Glance
{
  private const WIDE = [FieldKind::Objects, FieldKind::Image];

  /**
   * @param list<Column> $columns as this account reads them
   * @param list<string> $held the keys some record of the type holds (`Storage\Counts::filled`)
   */
  public function __construct(
    private array $columns,
    private TypeDefinition $type,
    private Profile $profile,
    private array $held,
  ) {}

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

  /* The date a timeline is ordered and filed by, and says as each row's day. */
  public function date(): ?Column
  {
    return array_find($this->columns, fn (Column $column): bool
      => in_array($column->field->kind, [FieldKind::Date, FieldKind::DateTime, FieldKind::DateOrTime], true));
  }

  /* A card's lines under its label; an asset's description is already its one field. */
  public function prose(): ?Column
  {
    return $this->media() ? null : array_find($this->columns, fn (Column $column): bool
      => $column->field->kind === FieldKind::LongText && in_array($column->field->key, $this->held, true));
  }

  /** @return list<Column> in the collection's order */
  public function shown(int $count, bool $new): array
  {
    return $this->drawn($count, $new, null);
  }

  /** A timeline's blank row: what a new record shows and its date, since a row made there is filed by it,
      and one with no cell for it stays under No date yet. An asset is made by its upload, which dates it.
      @return list<Column> in the collection's order */
  public function filed(int $count): array
  {
    $date = $this->date();

    return $this->drawn($count, true, $date?->editable === true && !$this->media() ? $date : null);
  }

  /** @return list<Column> */
  private function drawn(int $count, bool $new, ?Column $dated): array
  {
    $picked = $this->media() ? array_filter($this->columns, fn (Column $column): bool
      => $column->field->key === 'description') : array_slice($this->chosen(), 0, $count);
    $required = fn (Column $column): bool => $new && $column->editable && $column->field->required
      && $column !== $this->label();

    return array_values(array_filter($this->columns, fn (Column $column): bool
      => in_array($column, $picked, true) || $required($column) || $column === $dated));
  }

  /** @return list<Column> */
  private function chosen(): array
  {
    $narrow = array_filter($this->columns, fn (Column $column): bool
      => !in_array($column->field->kind, self::WIDE, true) && $column->field->key !== $this->type->namedBy()
      && in_array($column->field->key, $this->held, true) && !$this->unit($column->field));
    $chosen = [];
    foreach ($narrow as $column) {
      $block = $column->field->valueObjectOf;
      $stands = $block === null ? $column : $this->bearer(array_values(array_filter($narrow,
        fn (Column $kin): bool => $kin->field->valueObjectOf === $block)));
      $chosen = in_array($stands, $chosen, true) ? $chosen : [...$chosen, $stands];
    }

    return $chosen;
  }

  /* A currency an amount of the type is read with, so a price says *139,00 €* and never *Euro* alone. */
  private function unit(FieldDefinition $field): bool
  {
    return $field->list === ClosedList::Currency && $field->valueObject !== null
      && array_any($this->type->fields(), fn (FieldDefinition $each): bool => $each->kind === FieldKind::Number
        && $each->valueObject === $field->valueObject);
  }

  /** @param non-empty-list<Column> $block a value object's keys the collection holds, in its order */
  private function bearer(array $block): Column
  {
    $bearing = $this->profile->valueObject((string) $block[0]->field->valueObject)?->bearing ?? [];

    return array_find($block, fn (Column $column): bool
      => in_array($column->field->valueObjectKey, $bearing, true)) ?? $block[0];
  }
}
