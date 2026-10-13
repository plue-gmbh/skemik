<?php

declare(strict_types=1);

namespace Admin;

/* Fields that stand together on the record screen: one value object's under its
   name — one address block, one opening-hours block — or a single field
   with no heading. Built from the declaration on the field, so a type carrying a
   delivery and a billing address shows two blocks and not ten loose lines. */
final readonly class FieldBlock
{
  /** @param list<Column> $columns */
  public function __construct(public ?string $heading, public array $columns) {}

  /* The field's own label inside its block, without the block's name before it,
     whose colon French spaces. */
  public function labelOf(Column $column): string
  {
    return $this->heading === null ? $column->label
      : preg_replace('/^' . preg_quote($this->heading, '/') . '\h?: /u', '', $column->label, 1) ?? $column->label;
  }

  /**
   * @param list<Column> $columns in the collection's order
   * @return list<self>
   */
  public static function of(array $columns): array
  {
    $blocks = [];
    $open = null;
    foreach ($columns as $column) {
      $object = $column->field->valueObjectOf;
      if ($object !== null && $open === $object) {
        $last = array_pop($blocks);
        $blocks[] = new self($last->heading, [...$last->columns, $column]);
        continue;
      }
      $open = $object;
      $blocks[] = new self($object === null ? null : $column->field->block, [$column]);
    }

    return array_map(fn (self $block): self => count($block->columns) === 1 ? new self(null, $block->columns)
      : $block, $blocks);
  }
}
