<?php

declare(strict_types=1);

/* One field's cell, alike in every view that draws a record's values, so the
   save bar, the cell editor and the ticks find it by its record and its field
   wherever it stands: a button the editor opens where the account writes the
   field, else the value alone. An uploaded file's address and type are shown
   and never edited (`Domain\Entity::keeps`). Outside the table no heading names
   it, so its name is spoken before the value and titles it. */

if (!isset($template)) {
  return;
}
$key = $column->field->key;
$required = $row === null && $column->field->required;
$edits = $column->editable && !($row?->entity->keeps($key) ?? false);
$name = $named ?? null;
$spoken = $name === null ? '' : '<span class="visually-hidden">' . $template->escape($name) . ': </span>';
$marks = ($required && $edits ? ' data-required' : '') . $column->kindMarks($row?->entity)
  . ($name === null ? '' : ' title="' . $template->escape($name) . '"');
$shown = $spoken . '<span class="cell-shown">' . $cell . '</span>';
?>
<<?= $element ?> class="sheet-cell kind-cell-<?= $template->escape($column->field->kind->value) ?>"
  data-field="<?= $template->escape($key) ?>"<?= $marks ?>><?php if ($edits): ?><button type="button" class="cell"
  data-edit aria-describedby="sheet-hint"><?= $shown ?><?php if ($required): ?><span
  class="visually-hidden">, <?= $template->escape($words->get('sheet.required')) ?></span><?php endif ?></button><?php
  else: ?><span class="cell"><?= $shown ?></span><?php endif ?></<?= $element ?>>
