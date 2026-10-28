<?php

declare(strict_types=1);

/* The table view, a spreadsheet in a card that scrolls sideways inside itself,
   never the page. The rows are drawn here and the island only edits them, so the
   table reads without a script. A value object's columns have no hairline
   between them, since they move and leave as one. The first column opens the
   record, the one action always shown, and no row number stands, since it would
   be the row's place on this page. A new record is pending like any edit and made
   by a `POST` when saved, so a record the type would refuse is corrected before it
   exists rather than after. */

if (!isset($template)) {
  return;
}
$joins = fn (int $index): bool => $columns[$index]->field->valueObjectOf !== null
  && $columns[$index]->field->valueObjectOf === ($columns[$index + 1] ?? null)?->field->valueObjectOf;
?>
<div data-ticked></div>
<div class="sheet-card">
  <div class="sheet-scroll">
    <table class="sheet" data-sheet>
      <caption class="visually-hidden"><?= $template->escape($collection->name) ?></caption>
      <thead>
        <tr>
<?php if ($creates): ?>
          <th scope="col" class="sheet-tick"><?php if (count($rows) > 1): ?><input type="checkbox" data-tick-all
            aria-label="<?= $template->escape($words->get('sheet.tick_all')) ?>"><?php endif ?></th>
<?php endif ?>
          <th scope="col" class="sheet-open"><span
            class="visually-hidden"><?= $template->escape($words->get('sheet.actions')) ?></span></th>
          <th scope="col" class="sheet-spine" title="<?= $template->escape($words->get('sheet.spine'))
            ?>"><?= $template->escape($words->get('spine.slug')) ?></th>
<?php foreach ($columns as $index => $column): ?>
          <?= $template->render('partials/sheet-head', ['words' => $words, 'collection' => $collection,
            'column' => $column,
  'joined' => $joins($index), 'parameters' => $parameters, 'badges' => $badges]) ?>
<?php endforeach ?>
        </tr>
      </thead>
      <tbody data-rows>
<?php foreach ($rows as $row): ?>
<?= $template->render('partials/sheet-row', ['words' => $words, 'columns' => $columns, 'row' => $row,
  'items' => $items, 'ticks' => $creates, 'house' => $house]) ?>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php if ($rows === []): ?>
  <p class="sheet-empty" data-sheet-empty><?= $template->escape($words->get(match (true) {
    $total > 0 => 'sheet.past_end',
    $narrowed => 'query.nothing',
    default => 'sheet.none',
  })) ?></p>
<?php endif ?>
<?php if ($creates): ?>
  <template data-new-row><?= $template->render('partials/sheet-row', ['words' => $words, 'columns' => $columns,
    'row' => null, 'items' => $items, 'ticks' => true]) ?></template>
  <button type="button" class="sheet-add" data-add-row><?= $template->render('partials/icon',
    ['name' => 'plus']) ?><?= $template->escape($add) ?></button>
<?php endif ?>
  <?= $template->render('partials/sheet-pages', ['words' => $words, 'page' => $page, 'perPage' => $perPage,
    'total' => $total, 'shown' => count($rows), 'parameters' => $parameters]) ?>
</div>
<p id="sheet-hint" class="visually-hidden"><?= $template->escape($words->get('sheet.hint')) ?></p>
<?= $template->render('partials/save-bar', ['words' => $words, 'data' => $data, 'spine' => $spine]) ?>
