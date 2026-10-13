<?php

declare(strict_types=1);

namespace Admin;

/* One line of the sidebar, as `Sidebar` computed it for this account. `served`
   is the serving mark and `writers` the grant mark's names; the page draws
   neither without them, since a mark that does not apply is absent rather than
   grey. */
final readonly class SidebarEntry
{
  /* A type's glyph is the name of its drawing in `partials/glyphs`, the type's short name. */
  public static function glyphOf(string $type): string
  {
    return basename($type);
  }

  /** @param list<string> $writers the live keys that write here, by name */
  public function __construct(
    public string $glyph,
    public string $name,
    public string $slug,
    public int $count,
    public bool $served,
    public array $writers = [],
  ) {}

  /** @param list<string> $writers */
  public function writtenBy(array $writers): self
  {
    return new self($this->glyph, $this->name, $this->slug, $this->count, $this->served, $writers);
  }
}
