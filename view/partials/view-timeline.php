<?php

declare(strict_types=1);

/* The timeline view: the table's rows laid out otherwise, as the list's are —
   the same cells, the same save bar and the same ticks with their bar, the one
   for the page above the rows — filed by the type's date (`Admin\Glance::date`)
   under its months, each row a day (`partials/timeline-row`), and what has no
   date last under No date yet. Ordered by that date and nothing else, which
   the query bar says, and where the collection carries none by when each
   record was made (`Admin\Timeline`). + New record adds its pending row under
   No date yet, drawn empty and hidden until it holds one, and a save that
   dates a row, or makes one where the day is its making, reads the page
   again, since that files it (`view/scripts/sheet.js`). Where the page holds
   rows on both sides of the house's day, a line says Today between them, above
   today's own, so a calendar shows what comes next without a filter. */

if (!isset($template)) {
  return;
}
$dated = $glance->date();
$drawn = fn (?Admin\SheetRow $row): string => $template->render('partials/timeline-row', ['words' => $words,
  'columns' => $columns, 'row' => $row, 'items' => $items, 'ticks' => $creates, 'house' => $house,
  'glance' => $glance, 'timeline' => $timeline]);
$under = null;
$today = $timeline->today();
$descending = str_starts_with((string) ($parameters['sort'] ?? '-'), '-');
$reached = fn (?string $when): ?bool => $when === null ? null
  : ($descending ? $when <= $today : $when >= $today);
$before = null;
?>
<div data-ticked></div>
<div class="sheet-card">
  <div data-sheet="timeline" data-dated="<?= $template->escape((string) $dated?->field->key) ?>">
<?php if ($creates && count($rows) > 1): ?>
    <label class="item-tick-all"><input type="checkbox"
      data-tick-all><?= $template->escape($words->get('sheet.tick_all')) ?></label>
<?php endif ?>
    <ul class="item-list timeline" data-rows aria-label="<?= $template->escape($collection->name) ?>">
<?php foreach ($rows as $row): [$when, $month, $was] = [$timeline->when($row), $under, $before] ?>
<?php if (($before = $reached($when)) === true && $was === false): ?>
      <li class="timeline-now"><span><?= $template->escape($words->get('timeline.today')) ?></span></li>
<?php endif ?>
<?php if (($under = substr((string) $when, 0, 7)) !== $month): ?>
      <li class="timeline-month"><h2><?= $template->escape($when === null ? $words->get('timeline.undated')
        : $timeline->heading($when)) ?></h2></li>
<?php endif ?>
<?= $drawn($row) ?>
<?php endforeach ?>
<?php if ($creates && $dated !== null && $under !== ''): ?>
      <li class="timeline-month is-waiting"><h2><?= $template->escape($words->get('timeline.undated')) ?></h2></li>
<?php endif ?>
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
