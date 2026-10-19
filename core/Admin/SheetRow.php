<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\Locale;
use Profile\Reach;

/* One record as the table draws it: its label, its state as a reader meets it now
   — `scheduled` while a published row's start is ahead, `ended` once its end has passed — the
   type's own status word where the type has no public state (Paid, not
   Draft), each column's cell already rendered, and its edges as chips, which a
   picker starts from. Where another locale's words are edited, that locale and
   the authored record ride along for the editors' placeholders (`RecordWords`). `changed` is
   the day of its last change worded for the reader (`Moments`), which the spine chip's title says. */
final readonly class SheetRow
{
  /**
   * @param array<string, string> $cells rendered HTML by field key
   * @param array<string, array<int, Chip>> $chips by field key, in edge order, or by row for a value object's
   */
  public function __construct(
    public Entity $entity,
    public string $label,
    public string $state,
    public Reach $reach,
    public array $cells,
    public ?string $own = null,
    public array $chips = [],
    public ?Entity $authored = null,
    public string $changed = '',
    public ?Locale $words = null,
  ) {}

  public function withEntity(Entity $entity): self
  {
    return clone($this, ['entity' => $entity]);
  }
}
