<?php

declare(strict_types=1);

/* The grid view, its tiles standing free on the page: a tile per record
   (`partials/grid-tile`), its picture where there can be one, and the table's rows
   laid out otherwise — the same cells, the same save bar and the same ticks with
   their bar, the one for the page above the tiles. Its last tile is + New
   record, which adds a pending tile as the table adds a row, and in the media
   library Upload, since an asset of this house is mostly made by its file, and
   an empty library is that tile alone; the header's + File from elsewhere adds
   its pending tile ahead of it. */

if (!isset($template)) {
  return;
}
$library = $glance->media();
$drawn = fn (?Admin\SheetRow $row): string => $template->render('partials/grid-tile', ['words' => $words,
  'collection' => $collection, 'columns' => $columns, 'row' => $row, 'parameters' => $row === null ? [] : $parameters,
  'multilingual' => $multilingual, 'ticks' => $creates, 'house' => $house, 'glance' => $glance, 'uses' => $uses]);
$ending = $library ? $uploads : $creates;
?>
<div data-ticked></div>
<div data-sheet="grid">
<?php if ($creates && $rows !== []): ?>
  <label class="item-tick-all"><input type="checkbox"
    data-tick-all><?= $template->escape($words->get('sheet.tick_all')) ?></label>
<?php endif ?>
<?php if ($rows !== [] || $ending): ?>
  <ul class="grid" data-rows aria-label="<?= $template->escape($collection->name) ?>">
<?php foreach ($rows as $row): ?>
<?= $drawn($row) ?>
<?php endforeach ?>
<?php if ($ending && $library): ?>
    <li class="grid-new" data-rows-end><label for="upload-file"><?= $template->render('partials/icon',
      ['name' => 'upload']) ?><?= $template->escape($words->get('media.upload')) ?><span><?= $template->escape(
        $words->get('media.limit', ['limit' => $limit])) ?></span></label></li>
<?php elseif ($ending): ?>
    <li class="grid-new" data-rows-end><button type="button"
      data-add-row><?= $template->render('partials/icon',
      ['name' => 'plus']) ?><?= $template->escape($add) ?></button></li>
<?php endif ?>
  </ul>
<?php endif ?>
</div>
<?php if ($rows === [] && (!$ending || $total > 0 || $narrowed)): ?>
<p class="sheet-empty" data-sheet-empty><?= $template->escape($words->get(match (true) {
  $total > 0 => 'sheet.past_end',
  $narrowed => 'query.nothing',
  default => $library ? 'media.none_yet' : 'sheet.none',
})) ?></p>
<?php endif ?>
<?php if ($creates): ?>
<template data-new-row><?= $drawn(null) ?></template>
<?php endif ?>
<?= $template->render('partials/sheet-pages', ['words' => $words, 'page' => $page, 'perPage' => $perPage,
  'total' => $total, 'shown' => count($rows), 'parameters' => $parameters]) ?>
<p id="sheet-hint" class="visually-hidden"><?= $template->escape($words->get('sheet.hint')) ?></p>
<?= $template->render('partials/save-bar', ['words' => $words, 'data' => $data, 'spine' => $spine]) ?>
