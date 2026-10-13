<?php

declare(strict_types=1);

/* The list view: a record per row (`partials/list-row`), read down the label, and
   the table's rows laid out otherwise — the same cells, the same save bar and
   the same ticks with their bar, the one for the page above the rows. A few
   fields and no columns, so nothing to click to sort by: the query bar's select
   orders it. + New record below adds a pending row as the table's does. */

if (!isset($template)) {
  return;
}
$drawn = fn (?Admin\SheetRow $row): string => $template->render('partials/list-row', ['words' => $words,
  'collection' => $collection, 'columns' => $columns, 'row' => $row, 'parameters' => $row === null ? [] : $parameters,
  'multilingual' => $multilingual, 'ticks' => $creates, 'house' => $house, 'glance' => $glance]);
?>
<div data-ticked></div>
<div class="sheet-card">
  <div data-sheet="list">
<?php if ($creates && $rows !== []): ?>
    <label class="item-tick-all"><input type="checkbox"
      data-tick-all><?= $template->escape($words->get('sheet.tick_all')) ?></label>
<?php endif ?>
    <ul class="item-list" data-rows aria-label="<?= $template->escape($collection->name) ?>">
<?php foreach ($rows as $row): ?>
<?= $drawn($row) ?>
<?php endforeach ?>
    </ul>
  </div>
<?php if ($rows === []): ?>
  <p class="sheet-empty" data-sheet-empty><?= $template->escape($words->get(match (true) {
    $total > 0 => 'sheet.past_end',
    $narrowed => 'query.nothing',
    default => 'sheet.none',
  })) ?></p>
<?php endif ?>
<?php if ($creates): ?>
  <template data-new-row><?= $drawn(null) ?></template>
  <button type="button" class="sheet-add" data-add-row><?= $template->render('partials/icon',
    ['name' => 'plus']) ?><?= $template->escape($add) ?></button>
<?php endif ?>
  <?= $template->render('partials/sheet-pages', ['words' => $words, 'page' => $page, 'perPage' => $perPage,
    'total' => $total, 'shown' => count($rows), 'parameters' => $parameters]) ?>
</div>
<p id="sheet-hint" class="visually-hidden"><?= $template->escape($words->get('sheet.hint')) ?></p>
<?= $template->render('partials/save-bar', ['words' => $words, 'data' => $data, 'spine' => $spine]) ?>
