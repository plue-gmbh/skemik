<?php

declare(strict_types=1);

/* The Fields dialog (`Admin\FieldController`), for everyone who reads the
   collection: one list under one search, the fields it carries first, in order,
   each opening the popover a column's name opens (`partials/field-popover`),
   since only the table has columns to click. Under them, for `manageFields`,
   every field the type still offers, a value object one line with its count of
   fields. Every line stands beside its schema.org name, so a field has it
   before it exists. The offers are one form's submit buttons, so a field is
   added without a script; the script only narrows the list
   (`view/scripts/fields.js`). */

if (!isset($template)) {
  return;
}
$type = basename($collection->type);
$holder = $fields ? 'form method="post" action="/c/' . $template->escape($collection->slug->value) . '/fields"' : 'div';
$search = $words->get('field.search', ['n' => (string) (count($columns) + count($offers))]);
?>
<template id="fields">
  <p class="popover-label"><?= $template->escape($words->get('field.carried', ['type' => $type])) ?></p>
  <<?= $holder ?>>
<?php if ($fields): ?>
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
<?php endif ?>
    <input class="popover-input" type="search" autocomplete="off" data-field-search
      placeholder="<?= $template->escape($search) ?>" aria-label="<?= $template->escape($search) ?>">
    <div class="options is-scrolling">
<?php foreach ($columns as $column):
  $key = $column->field->key;
  $names = isset($badges[$key]) ? $badges[$key]->names() : '' ?>
      <button type="button" class="option" aria-haspopup="dialog"
        data-field-popover="field-<?= $template->escape($key) ?>"
        data-field-carried><?= trim($template->render('partials/kind', ['kind' => $column->field->kind]))
        ?><span><?= $template->escape($column->label) ?></span><?php if ($column->feed): ?><span class="public-mark"
        title="<?= $template->escape($words->get('sheet.public')) ?>"><?= $template->render('partials/icon',
        ['name' => 'unlocked']) ?></span><span
        class="visually-hidden">, <?= $template->escape($words->get('sheet.public')) ?></span><?php endif ?><?php
        if ($names !== ''): $here = $words->get('grant.here', ['names' => $names]) ?><span class="grant-mark"
        title="<?= $template->escape($here) ?>"><?= $template->render('partials/glyph', ['name' => 'key'])
        ?></span><span class="visually-hidden">, <?= $template->escape($here) ?></span><?php endif ?><span
        class="option-term"><?= $template->escape($column->parameter) ?></span></button>
<?php endforeach ?>
<?php foreach ($offers as $offer): ?>
      <button type="submit" class="option is-offer" name="key" value="<?= $template->escape($offer->key) ?>"
        data-field-offer><?= trim($template->render('partials/kind', ['kind' => $offer->kind]))
        ?><span><?= $template->escape($offer->label) ?><?= $offer->fields > 1
          ? ' · ' . $template->escape($words->get('field.whole', ['n' => (string) $offer->fields])) : '' ?><?=
          $offer->computed ? ' · ' . $template->escape($words->get('field.computed')) : '' ?></span><span
        class="option-term"><?= $template->escape($offer->property) ?></span></button>
<?php endforeach ?>
    </div>
    <p class="popover-hint" data-field-none hidden><?= $template->escape($fields ? $words->get('field.none',
      ['type' => $type]) : $words->get('query.nothing')) ?></p>
<?php if ($fields && $offers === []): ?>
    <p class="popover-hint"><?= $template->escape($words->get('field.all_held', ['type' => $type])) ?></p>
<?php endif ?>
  </<?= $fields ? 'form' : 'div' ?>>
</template>
