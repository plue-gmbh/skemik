<?php

declare(strict_types=1);

/* One column's header cell: the kind's glyph and the label, one button that
   opens the column's popover (`partials/field-popover`, drawn once on the page
   for every view by `pages/collection`). Everything else is a
   mark and no control, since the popover changes it and the query bar sorts: a
   green open lock on a public field, a key while a live grant holds, the arrow of
   the sort. */

if (!isset($template)) {
  return;
}
$key = $column->field->key;
$sorted = match ((string) ($parameters['sort'] ?? '')) {
  '-' . $column->parameter => 'descending',
  $column->parameter => 'ascending',
  default => null,
};
$names = isset($badges[$key]) ? $badges[$key]->names() : '';
$state = $words->get($column->feed ? 'sheet.public' : 'sheet.internal');
?>
<th scope="col" class="sheet-field<?= $joined ? ' is-joined' : '' ?>"<?= $sorted === null ? '' : ' aria-sort="'
  . $sorted . '"' ?>><button type="button" class="sheet-head" aria-haspopup="dialog"
  data-field-popover="field-<?= $template->escape($key) ?>"><?= trim($template->render('partials/kind',
  ['kind' => $column->field->kind])) ?><?= $template->escape($column->label) ?><?php if ($column->feed): ?><span
  class="public-mark" title="<?= $template->escape($state) ?>"><?= $template->render('partials/icon',
  ['name' => 'unlocked']) ?></span><?php endif ?><?php if ($sorted !== null): ?><?= $template->render('partials/icon',
  ['name' => $sorted]) ?><?php endif ?><?php if ($names !== ''): $granted = $words->get('grant.here',
  ['names' => $names]) ?><span class="grant-mark" title="<?= $template->escape($granted) ?>"
  ><?= $template->render('partials/glyph', ['name' => 'key']) ?></span><span
  class="visually-hidden">, <?= $template->escape($granted) ?></span><?php endif ?><span
  class="visually-hidden">, <?= $template->escape($state) ?></span></button></th>
