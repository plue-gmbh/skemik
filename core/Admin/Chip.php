<?php

declare(strict_types=1);

namespace Admin;

use Domain\Locale;
use Domain\Relation;
use Profile\FieldKind;
use Profile\Qualifier;

/* One edge's target as a cell draws it, resolved for the account that reads it.
   A target that is gone and one the reader may not see are the same chip, so the
   cell never says which, nor what the edge says of it. A target a collection
   holds links to its record screen; an image carries its description, which the
   record screen captions it with, and its preview. */
final readonly class Chip
{
  public function __construct(
    public string $label,
    public string $title,
    public string $glyph,
    public bool $gone = false,
    public bool $noAlt = false,
    public ?string $href = null,
    public string $caption = '',
    public ?string $preview = null,
    public ?Relation $edge = null,
  ) {}

  /* As a picker lists it: by its place in the field's list, which is what a save
     sends back to keep it (`FieldEdits`), and never by its target, since a chip
     drawn as gone must not name what it hides; its link is the cell's, which an
     edited cell does not draw. An edge's words come as its lines
     take them, and in another locale that locale's own, the ones it was written
     with as their placeholders.
     @return array<string, mixed> */
  public function held(int $index, ?Locale $words = null): array
  {
    $drawn = ['edge' => $index, 'label' => $this->label, 'glyph' => $this->glyph, 'gone' => $this->gone,
      'preview' => $this->preview, 'href' => $this->href];
    if ($this->edge === null) {
      return $drawn;
    }
    $lines = $words === null ? Qualifier::lines($this->edge->predicate, $this->edge->objectType)
      : array_fill_keys(Qualifier::words(), FieldKind::Text);
    $said = array_intersect_key($this->edge->qualifiers->document(), $lines);

    return [...$drawn, 'type' => $this->edge->objectType, 'lines' => (object) $lines,
      'qualifiers' => (object) ($words === null ? $said : $this->edge->wordsIn($words)),
      ...($words === null ? [] : ['hints' => (object) $said])];
  }

  /* The edge beside its target, for the words it says of it. */
  public function saying(Relation $edge): self
  {
    return $this->gone ? $this : clone($this, ['edge' => $edge]);
  }

  public static function gone(): self
  {
    return new self('', '', 'gone', true);
  }
}
