<?php

declare(strict_types=1);

/* The timeline view: the list's rows laid out otherwise, with the same cells,
   save bar and ticks, filed under months by the type's date (`Admin\Glance::date`),
   each row a day (`partials/timeline-row`), and what has no date last under No
   date yet. Ordered by that date alone, or by when each record was made where the
   type has none (`Admin\Timeline`). + New record's pending row waits hidden under
   No date yet, and a save that dates a row reads the page again, since that files
   it. Where the page holds rows on both sides of the install's day, a line says
   Today between them, so a calendar shows what comes next without a filter.
   Handed `$rows`, `$columns`, `$collection`, `$items`, `$glance`, `$firstLocale`, `$timeline`, `$creates`,
   `$add`, `$total`, `$narrowed`, `$page`, `$perPage`, `$parameters`, `$data`, `$spine` and `$words`. */

if (!isset($template)) {
  return;
}
$dated = $glance->date();
$drawn = fn (?Admin\SheetRow $row): string => $template->render('partials/timeline-row', ['words' => $words,
  'columns' => $columns, 'row' => $row, 'items' => $items, 'ticks' => $creates, 'firstLocale' => $firstLocale,
  'glance' => $glance, 'timeline' => $timeline]);
$under = null;
$today = $timeline->today();
$descending = str_starts_with((string) ($parameters['sort'] ?? '-'), '-');
$reached = fn (?string $when): ?bool => $when === null ? null
  : ($descending ? $when <= $today : $when >= $today);
$before = null;
?>
<div class="sheet-card">
  <div data-sheet="timeline" data-dated="<?= $template->escape((string) $dated?->field->key) ?>">
<?php if ($creates && count($rows) > 1): ?>
    <label class="item-tick-all"><input type="checkbox"
      data-tick-all><?= $template->escape($words->get('sheet.tick_all')) ?></label>
<?php endif ?>
    <ul class="item-list timeline" data-rows aria-label="<?= $template->escape($collection->name) ?>">
<?php foreach ($rows as $row):
  $when = $timeline->when($row);
  $month = $under;
  $was = $before ?>
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
<?= $template->render('partials/save-bar', ['data' => $data, 'spine' => $spine]) ?>
