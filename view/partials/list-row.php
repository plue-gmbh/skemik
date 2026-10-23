<?php

declare(strict_types=1);

/* One row of the list view, drawn by the collection screen and by the answer to
   a new record's `POST`, as the table's are (`partials/sheet-row`): the tick
   where the account writes, the picture where the type has one, it and the label
   both the link to the record, the spine chip beneath, then the collection's
   first fields (`Admin\Glance`) as the table's cells. Without a record it is the blank
   row + New record clones, its label a cell until it has an address. */

if (!isset($template)) {
  return;
}
$cell = fn (Admin\Column $column, string $named): string => $template->render('partials/sheet-cell', [
  'words' => $words, 'column' => $column, 'row' => $row, 'element' => 'span', 'named' => $named,
  'cell' => $row?->cells[$column->field->key]]);
$opens = array_any($columns, fn (Admin\Column $column): bool => $column->editable);
$own = $items->own($row);
$label = $glance->label();
$alt = $glance->media() && str_starts_with((string) $row?->entity->props->string('encoding_format'), 'image/');
$pictured = $glance->media() || $glance->image() !== null;
$picture = $template->render('partials/item-picture', ['row' => $row, 'glance' => $glance,
  'collection' => $collection, 'class' => 'list-picture']);
$address = $items->address($row);
?>
<li class="list-row" data-record="<?= $row === null ? '' : $template->escape($row->entity->id->value) ?>">
<?php if ($ticks ?? false): ?>
  <span class="item-tick"><?php if ($row !== null): ?><input type="checkbox" data-tick
    aria-label="<?= $template->escape($words->get('sheet.tick', ['label' => $row->label])) ?>"><?php endif ?></span>
<?php endif ?>
<?php if ($pictured && $address === null): ?>
  <?= $picture ?>
<?php elseif ($pictured): ?>
  <a class="item-open" href="<?= $template->escape($address) ?>" tabindex="-1" aria-hidden="true"><?= $picture ?></a>
<?php endif ?>
  <span class="list-main">
<?php if ($row !== null): ?>
    <a class="item-label" href="<?= $template->escape($address) ?>"><?= $template->escape($row->label) ?></a>
    <?= $template->render('partials/spine-chip', ['words' => $words, 'row' => $row, 'prefix' => '',
      'translations' => $own, 'opens' => $opens, 'current' => $house ?? null]) ?>
<?php else: ?>
<?php if ($label !== null): ?>
    <?= $cell($label, $label->label) ?>
<?php endif ?>
    <span class="spine-chip is-new"><?= $template->escape($words->get('sheet.new')) ?></span>
<?php endif ?>
  </span>
  <span class="list-cells">
<?php foreach ($glance->shown(3, $row === null) as $column): ?>
    <?= $cell($column, $alt && $column->field->key === 'description' ? $words->get('media.alt') : $column->label) ?>
<?php endforeach ?>
  </span>
</li>
