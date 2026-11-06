<?php

declare(strict_types=1);

/* One field's cell, alike in every view that draws a record's values, so the
   save bar, the cell editor and the ticks find it by its record and its field
   wherever it stands: a button the editor opens where the account writes the
   field, else the value alone. A reference's chips are links only where the
   field is not edited, so a button never holds a link. An uploaded
   file's address and type are shown and never edited (`Domain\Entity::keeps`).
   Outside the table no heading names it, so its name is spoken before the
   value and titles it. Handed `$column`, `$row`, null for the blank row, `$element` and `$words`;
   `$named` and `$cell` may be left out, and with no cell it draws the empty one + New record clones. */

if (!isset($template)) {
  return;
}
$named ??= null;
$cell ??= $template->render('partials/cell', ['words' => $words, 'kind' => Profile\FieldKind::Text,
  'value' => null, 'chips' => [], 'empty' => true, 'shown' => '']);
$key = $column->field->key;
$required = $row === null && $column->field->required;
$edits = $column->editable && !($row?->entity->keeps($key) ?? false);
$spoken = $named === null ? '' : '<span class="visually-hidden">' . $template->escape($named) . ': </span>';
$marks = ($required && $edits ? ' data-required' : '') . $column->kindMarks($row?->entity)
  . ($named === null ? '' : ' title="' . $template->escape($named) . '"');
$shown = $spoken . '<span class="cell-shown">' . $cell . '</span>';
$note = $required ? ', ' . $words->get('sheet.required') : '';
?>
<<?= $element ?> class="sheet-cell kind-cell-<?= $template->escape($column->field->kind->value) ?>"
  data-field="<?= $template->escape($key) ?>"<?= $marks ?>><?php if ($edits): ?><button type="button" class="cell"
  data-edit aria-describedby="sheet-hint"><?= $shown ?><?php if ($required): ?><span
  class="visually-hidden"><?= $template->escape($note) ?></span><?php endif ?></button><?php
  else: ?><span class="cell"><?= $shown ?></span><?php endif ?></<?= $element ?>>
