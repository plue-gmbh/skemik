<?php

declare(strict_types=1);

namespace Admin;

/* One line of the sidebar, as `Sidebar` computed it for this account. `served`
   is the serving mark and `holders` the grant mark's names; the page draws
   neither without them, since a mark that does not apply is absent rather than
   grey. */
final readonly class SidebarEntry
{
  /* A type's glyph is the name of its drawing in `partials/glyphs`, the type's short name. */
  public static function glyphOf(string $type): string
  {
    return basename($type);
  }

  /** @param list<string> $holders the live keys that hold a rule here, by name */
  public function __construct(
    public string $glyph,
    public string $name,
    public string $slug,
    public int $count,
    public bool $served,
    public array $holders = [],
  ) {}

  /** @param list<string> $holders */
  public function heldBy(array $holders): self
  {
    return new self($this->glyph, $this->name, $this->slug, $this->count, $this->served, $holders);
  }
}
