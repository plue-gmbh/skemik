<?php

declare(strict_types=1);

/* One card of the cards view, drawn by the collection screen and by the answer
   to a new record's `POST`, as the table's rows are: the tick where the account
   writes, the picture where the collection carries an image, it and the label
   both the link to the record, the spine chip beneath, the first line of its
   long text, and the collection's first fields (`Admin\Glance`) as the table's
   cells, each named on the card, since a card has room for the name a list row
   only titles. Without a record it is the blank card + New record clones, its
   label a cell until it has an address. */

if (!isset($template)) {
  return;
}
$entity = $row?->entity;
$cell = fn (Admin\Column $column, string $named): string => $template->render('partials/sheet-cell', [
  'words' => $words, 'column' => $column, 'row' => $row, 'element' => 'span', 'named' => $named,
  'cell' => $row?->cells[$column->field->key]]);
$opens = array_any($columns, fn (Admin\Column $column): bool => $column->editable);
$own = $items->own($row);
$label = $glance->label();
$prose = $glance->prose();
$alt = $glance->media() && str_starts_with((string) $entity?->props->string('encoding_format'), 'image/');
$fields = array_filter($glance->shown(4, $row === null), fn (Admin\Column $column): bool => $column !== $prose);
$pictured = $glance->media() || $glance->image() !== null;
$picture = $template->render('partials/item-picture', ['row' => $row, 'glance' => $glance,
  'collection' => $collection, 'class' => 'card-picture']);
$address = $items->address($row);
?>
<li class="item-card" data-record="<?= $row === null ? '' : $template->escape($entity->id->value) ?>">
<?php if (($ticks ?? false) && $row !== null): ?>
  <span class="item-tick"><input type="checkbox" data-tick aria-label="<?= $template->escape($words->get('sheet.tick',
    ['label' => $row->label])) ?>"></span>
<?php endif ?>
<?php if ($pictured && $address === null): ?>
  <?= $picture ?>
<?php elseif ($pictured): ?>
  <a class="item-open" href="<?= $template->escape($address) ?>" tabindex="-1" aria-hidden="true"><?= $picture ?></a>
<?php endif ?>
  <span class="card-main">
<?php if ($address !== null): ?>
    <a class="item-label" href="<?= $template->escape($address) ?>"><?= $template->escape($row->label) ?></a>
    <?= $template->render('partials/spine-chip', ['words' => $words, 'row' => $row, 'prefix' => '',
      'translations' => $own, 'opens' => $opens, 'current' => $house ?? null]) ?>
<?php else: ?>
<?php if ($label !== null): ?>
    <?= $cell($label, $label->label) ?>
<?php endif ?>
    <span class="spine-chip is-new"><?= $template->escape($words->get('sheet.new')) ?></span>
<?php endif ?>
<?php if ($prose !== null): ?>
    <span class="card-prose"><?= $cell($prose, $prose->label) ?></span>
<?php endif ?>
  </span>
<?php if ($fields !== []): ?>
  <span class="card-fields">
<?php foreach ($fields as $column): $named = $alt && $column->field->key === 'description' ? $words->get('media.alt')
  : $column->label ?>
    <span class="card-name" aria-hidden="true"><?= $template->escape($named) ?></span>
    <?= $cell($column, $named) ?>
<?php endforeach ?>
  </span>
<?php endif ?>
</li>
