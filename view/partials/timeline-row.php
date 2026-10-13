<?php

declare(strict_types=1);

/* One row of the timeline, drawn by the collection screen and by the answer to
   a new record's `POST`, as the list's are (`partials/list-row`): the tick where
   the account writes, its day as the house lives it, the label as the link to
   the record with the spine chip beneath, then the fields the type shows
   (`Admin\Glance`) as the table's cells, its date among them, since that is
   where it is edited. The answer to a `POST` has no day to draw: a row saved
   with a date is read again with the page. Without a record it is the blank
   row + New record clones, its label a cell until it has an address. */

if (!isset($template)) {
  return;
}
$empty = $template->render('partials/cell', ['words' => $words, 'kind' => Profile\FieldKind::Text, 'value' => null,
  'chips' => [], 'empty' => true, 'shown' => '']);
$cell = fn (Admin\Column $column): string => $template->render('partials/sheet-cell', [
  'words' => $words, 'column' => $column, 'row' => $row, 'element' => 'span', 'named' => $column->label,
  'cell' => $row === null ? $empty : $row->cells[$column->field->key]]);
$opens = array_any($columns, fn (Admin\Column $column): bool => $column->editable);
$own = ($multilingual ?? false) && $row !== null ? [new Admin\Translation($row->entity->locale->tag,
  $row->entity)] : null;
$label = $glance->label();
$when = ($timeline ?? null)?->when($row);
$day = $when === null ? null : $timeline->day($when);
?>
<li class="list-row timeline-row" data-record="<?= $row === null ? '' : $template->escape($row->entity->id->value) ?>">
<?php if ($ticks ?? false): ?>
  <span class="item-tick"><?php if ($row !== null): ?><input type="checkbox" data-tick
    aria-label="<?= $template->escape($words->get('sheet.tick', ['label' => $row->label])) ?>"><?php endif ?></span>
<?php endif ?>
  <span class="timeline-day"><?php if ($day !== null): ?><time datetime="<?= $day->format('Y-m-d') ?>"><span
    class="timeline-date"><?= $template->escape($timeline->says($day, 'd')) ?></span> <span
    class="timeline-weekday"><?= $template->escape($timeline->says($day, 'EEE')) ?></span></time><?php endif ?></span>
  <span class="list-main">
<?php if ($row !== null): ?>
    <a class="item-label" href="<?= $template->escape(Admin\Rows::address($collection, $row->entity,
      http_build_query(array_diff_key($parameters, ['in' => true])))) ?>"><?= $template->escape($row->label) ?></a>
    <?= $template->render('partials/spine-chip', ['words' => $words, 'row' => $row, 'prefix' => '',
      'translations' => $own, 'opens' => $opens, 'current' => $house ?? null]) ?>
<?php else: ?>
<?php if ($label !== null): ?>
    <?= $cell($label) ?>
<?php endif ?>
    <span class="spine-chip is-new"><?= $template->escape($words->get('sheet.new')) ?></span>
<?php endif ?>
  </span>
  <span class="list-cells">
<?php foreach ($glance->shown(3, $row === null) as $column): ?>
    <?= $cell($column) ?>
<?php endforeach ?>
  </span>
</li>
