<?php

declare(strict_types=1);

/* The cards view, its cards standing free on the page as the grid's tiles do: a
   card per record (`partials/card`), wider than a tile for the words it reads,
   and the table's rows laid out otherwise — the same cells, the same save bar
   and the same ticks with their bar, the one for the page above the cards. No
   column to click, so the query bar's select orders it. Its last card is + New
   record, which adds a pending card as the table adds a row. */

if (!isset($template)) {
  return;
}
$drawn = fn (?Admin\SheetRow $row): string => $template->render('partials/card', ['words' => $words,
  'collection' => $collection, 'columns' => $columns, 'row' => $row, 'parameters' => $row === null ? [] : $parameters,
  'multilingual' => $multilingual, 'ticks' => $creates, 'house' => $house, 'glance' => $glance]);
?>
<div data-ticked></div>
<div data-sheet="cards">
<?php if ($creates && $rows !== []): ?>
  <label class="item-tick-all"><input type="checkbox"
    data-tick-all><?= $template->escape($words->get('sheet.tick_all')) ?></label>
<?php endif ?>
<?php if ($rows !== [] || $creates): ?>
  <ul class="cards" data-rows aria-label="<?= $template->escape($collection->name) ?>">
<?php foreach ($rows as $row): ?>
<?= $drawn($row) ?>
<?php endforeach ?>
<?php if ($creates): ?>
    <li class="grid-new" data-rows-end><button type="button"
      data-add-row><?= $template->render('partials/icon',
      ['name' => 'plus']) ?><?= $template->escape($add) ?></button></li>
<?php endif ?>
  </ul>
<?php endif ?>
</div>
<?php if ($rows === [] && (!$creates || $total > 0 || $narrowed)): ?>
<p class="sheet-empty" data-sheet-empty><?= $template->escape($words->get(match (true) {
  $total > 0 => 'sheet.past_end',
  $narrowed => 'query.nothing',
  default => 'sheet.none',
})) ?></p>
<?php endif ?>
<?php if ($creates): ?>
<template data-new-row><?= $drawn(null) ?></template>
<?php endif ?>
<?= $template->render('partials/sheet-pages', ['words' => $words, 'page' => $page, 'perPage' => $perPage,
  'total' => $total, 'shown' => count($rows), 'parameters' => $parameters]) ?>
<p id="sheet-hint" class="visually-hidden"><?= $template->escape($words->get('sheet.hint')) ?></p>
<?= $template->render('partials/save-bar', ['words' => $words, 'data' => $data, 'spine' => $spine]) ?>
