<?php

declare(strict_types=1);

/* One row of the table view: the collection screen draws each with it, and so
   does the answer to a new record's `POST`, so the row the island made is
   replaced by the one the page would have drawn. Without a record it is the
   blank row + New record clones: its cells empty and edited as on any row,
   its prose too, so a type that requires a text is made here, and what the type
   requires marked while it is still empty, before a save is refused for it
   (`partials/sheet-cell`). The tick stands where the account writes, and a row
   not made yet has none to tick. */

if (!isset($template)) {
  return;
}
$empty = $template->render('partials/cell', ['words' => $words, 'kind' => Profile\FieldKind::Text, 'value' => null,
  'chips' => [], 'empty' => true, 'shown' => '']);
$opens = array_any($columns, fn (Admin\Column $column): bool => $column->editable);
$open = $row === null ? null
  : Admin\Rows::address($collection, $row->entity, http_build_query(array_diff_key($parameters, ['in' => true])));
$own = ($multilingual ?? false) && $row !== null ? [new Admin\Translation($row->entity->locale->tag,
  $row->entity)] : null;
?>
        <tr data-record="<?= $row === null ? '' : $template->escape($row->entity->id->value) ?>">
<?php if ($ticks ?? false): ?>
          <td class="sheet-tick"><?php if ($row !== null): ?><input type="checkbox" data-tick
            aria-label="<?= $template->escape($words->get('sheet.tick',
            ['label' => $row->label])) ?>"><?php endif ?></td>
<?php endif ?>
          <td class="sheet-open"><?php if ($row !== null): ?><a class="row-open"
            href="<?= $template->escape((string) $open) ?>" title="<?= $template->escape($words->get('record.open',
            ['label' => $row->label])) ?>"><?= $template->render('partials/icon', ['name' => 'open']) ?><span
            class="visually-hidden"><?= $template->escape($words->get('record.open',
            ['label' => $row->label])) ?></span></a><?php endif ?></td>
          <td class="sheet-spine"><?= $row === null ? '<span class="spine-chip is-new">'
            . $template->escape($words->get('sheet.new')) . '</span>' : $template->render('partials/spine-chip',
            ['words' => $words, 'row' => $row, 'prefix' => '', 'translations' => $own, 'opens' => $opens,
            'current' => $house ?? null]) ?></td>
<?php foreach ($columns as $column): ?>
          <?= $template->render('partials/sheet-cell', ['words' => $words, 'column' => $column, 'row' => $row,
  'element' => 'td', 'cell' => $row === null ? $empty : $row->cells[$column->field->key]]) ?>
<?php endforeach ?>
        </tr>
