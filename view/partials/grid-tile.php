<?php

declare(strict_types=1);

/* One tile of the grid view, drawn by the collection screen and by the answer
   to a new record's `POST`, as the table's rows are: the tick where the account
   writes, the picture where the collection carries an image or is the library,
   and the label, both the link to the record, the spine chip beneath, then the
   collection's first two fields (`Admin\Glance`) as the table's cells. An asset's tile
   says its pixels or its kind and how often it is used, under the reader's
   scope as its record screen counts it, and its one field is the description,
   titled alt text on a picture and marked where it is missing. Without a record
   it is the blank tile + New record clones, its label a cell until it has an
   address. */

if (!isset($template)) {
  return;
}
$entity = $row?->entity;
$opens = array_any($columns, fn (Admin\Column $column): bool => $column->editable);
$own = $items->own($row);
$label = $glance->label();
$mime = (string) $entity?->props->string('encoding_format');
$alt = $glance->media() && str_starts_with($mime, 'image/');
$missing = '<span class="no-alt">' . $template->escape($words->get('cell.no_alt')) . '</span>';
$shown = fn (Admin\Column $column): ?string => match (true) {
  $row === null => null,
  $alt && $column->field->key === 'description' && trim((string) $entity->props->string('description')) === ''
    => $missing,
  default => $row->cells[$column->field->key],
};
$cell = fn (Admin\Column $column, string $named): string => $template->render('partials/sheet-cell', [
  'words' => $words, 'column' => $column, 'row' => $row, 'element' => 'span', 'named' => $named,
  'cell' => $shown($column)]);
$address = $items->address($row);
$pictured = $glance->media() || $glance->image() !== null;
$picture = $template->render('partials/item-picture', ['row' => $row, 'glance' => $glance,
  'collection' => $collection, 'class' => 'grid-picture']);
$width = $entity?->props->int('width');
$height = $entity?->props->int('height');
$used = $uses[$entity?->id->value ?? ''] ?? 0;
?>
<li class="grid-tile" data-record="<?= $row === null ? '' : $template->escape($entity->id->value) ?>">
<?php if (($ticks ?? false) && $row !== null): ?>
  <span class="item-tick"><input type="checkbox" data-tick aria-label="<?= $template->escape($words->get('sheet.tick',
    ['label' => $row->label])) ?>"></span>
<?php endif ?>
<?php if ($pictured && $address === null): ?>
  <?= $picture ?>
<?php elseif ($pictured): ?>
  <a class="item-open" href="<?= $template->escape($address) ?>" tabindex="-1" aria-hidden="true"><?= $picture ?></a>
<?php endif ?>
  <span class="grid-caption">
<?php if ($row !== null): ?>
    <a class="item-label" href="<?= $template->escape($address) ?>"><?= $template->escape($row->label) ?></a>
    <?= $template->render('partials/spine-chip', ['words' => $words, 'row' => $row, 'prefix' => '',
      'translations' => $own, 'opens' => $opens, 'current' => $house ?? null]) ?>
<?php elseif ($label !== null): ?>
    <?= $cell($label, $label->label) ?>
<?php endif ?>
<?php if ($row === null): ?>
    <span class="spine-chip is-new"><?= $template->escape($words->get('sheet.new')) ?></span>
<?php elseif ($glance->media()): ?>
    <span class="grid-facts"><?= $template->escape($width !== null && $height !== null ? $width . '×'
      . $height : $words->get('media.kind.' . (in_array(strtok($mime, '/'), ['image', 'video', 'audio'],
      true) ? strtok($mime, '/') : 'document'))) ?> · <span
      class="grid-used<?= $used === 0 ? ' is-unused' : '' ?>"><?= $template->escape($used === 0
      ? $words->get('media.unused') : $words->get('media.used', ['n' => (string) $used])) ?></span></span>
<?php endif ?>
<?php foreach ($glance->shown(2, $row === null) as $column): ?>
    <?= $cell($column, $alt && $column->field->key === 'description' ? $words->get('media.alt') : $column->label) ?>
<?php endforeach ?>
  </span>
</li>
