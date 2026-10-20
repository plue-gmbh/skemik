<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\TypeDefinition;

/* One field of a collection as a view draws it for one account, and the name
   the address filters and sorts it by. `editable` is whether this account has an
   editor for it: every kind is edited from its cell — in place, or in the popover
   its kind opens: prose's textarea, a select's options, a reference's picker, a
   list's entries (`partials/cell-editor`), and an image the same picker over the
   media library; the record screen gives prose its full width. `uploads` is
   whether that picker takes a new file too: on a field pointing at the library,
   for an account that writes it. A field nobody authors — computed at emit, kept
   by a channel — has no editor anywhere, and `FieldEdits` refuses it as well,
   since a hidden input is not a rule. */
final readonly class Column
{
  private const PROSE = [FieldKind::Markdown, FieldKind::LongText];

  public function __construct(
    public FieldDefinition $field,
    public string $label,
    public bool $feed,
    public bool $editable,
    public string $parameter,
    public bool $uploads,
    public ?string $url = null,
    public ?string $objectUrl = null,
  ) {}

  /* Every field sorts but a list of objects, which has no one value to order by. */
  public function sorts(): bool
  {
    return $this->field->kind !== FieldKind::Objects;
  }

  /* A list of long texts is entries, each short enough for the list's own editor. */
  public function prose(): bool
  {
    return in_array($this->field->kind, self::PROSE, true) && !$this->field->repeatable;
  }

  /* What the cell says of a specialisation's field: whose it is, and that it is
     off where the record is not marked as that kind. A new row is no kind yet. */
  public function kindMarks(?Entity $entity): string
  {
    $kind = $this->field->ofKind;
    if ($kind === null) {
      return '';
    }
    $kinds = (array) ($entity?->props->document()[TypeDefinition::KIND] ?? []);

    return ' data-of-kind="' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '"'
      . (in_array($kind, $kinds, true) ? '' : ' data-off');
  }
}
