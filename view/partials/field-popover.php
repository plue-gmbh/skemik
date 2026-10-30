<?php

declare(strict_types=1);

/* A column's one popover, opened from its name in the header: the field's
   schema.org property, linked where the vocabulary has a page, and its grants
   folded behind one line, since they are read far less often than the field is
   set. No sentence explains what the header already marks. Whether a field
   leaves is the house's and switched here; its name, key and kind are the type's
   and only shown. Remove names the grants it breaks, since their rules would fail
   closed and say nothing, and a † has no such button, since the collection could
   not hold its type without it. Its form carries the screen's query, so the view
   comes back as it was narrowed. */

if (!isset($template)) {
  return;
}
$key = $column->field->key;
$action = '/c/' . $collection->slug->value . '/fields/' . $key;
$breaks = [];
foreach ($entry->keys ?? [] as $each) {
  foreach ($badges[$each]->reviews ?? [] as $review) {
    $breaks[$review->token->label] = true;
  }
}
$names = isset($badges[$key]) ? $badges[$key]->names() : '';
$link = fn (string $url, bool $whole): string => sprintf(
  '<a href="%s" target="_blank" rel="noreferrer" title="%s">%s</a>', $template->escape($url),
    $template->escape($words->get('collection.proof', ['type' => basename($url)])),
  $template->escape($whole ? substr($url, strlen('https://')) : basename($url)));
$grants = !isset($badges[$key]) ? ''
  : sprintf('<div class="field-grants"><button type="button" class="button-quiet is-grant%s"'
  . ' aria-expanded="false" data-grant-open>%s</button><div class="popover-part" hidden data-grant-part>%s</div></div>',
  $names === '' ? '' : ' is-live',
  $template->escape($names === '' ? $words->get('grant.give_field', ['anchor' => $column->label])
    : $words->get('grant.here', ['names' => $names])),
  $template->render('partials/grant-panel', ['words' => $words, 'badge' => $badges[$key], 'folded' => true,
    'collection' => $collection, 'csrf' => $csrf, 'back' => $back, 'until' => $until]));
?>
<template id="field-<?= $template->escape($key) ?>">
  <div class="popover-head">
    <p class="popover-source"><?= $column->objectUrl === null ? '' : $link($column->objectUrl,
      true) . ' › ' ?><?= $column->url === null ? $template->escape($column->parameter) : $link($column->url,
      $column->objectUrl === null) ?></p>
<?php if ($entry !== null): ?>
    <span class="field-move">
      <button type="button" class="button-quiet" data-field-move="left"<?= $entry->first ? ' disabled' : '' ?>
        title="<?= $template->escape($words->get('field.left')) ?>"><?= $template->render('partials/icon',
        ['name' => 'left']) ?><span
        class="visually-hidden"><?= $template->escape($words->get('field.left')) ?></span></button>
      <button type="button" class="button-quiet" data-field-move="right"<?= $entry->last ? ' disabled' : '' ?>
        title="<?= $template->escape($words->get('field.right')) ?>"><?= $template->render('partials/icon',
        ['name' => 'right']) ?><span
        class="visually-hidden"><?= $template->escape($words->get('field.right')) ?></span></button>
    </span>
<?php endif ?>
  </div>
<?php if ($entry === null): ?>
  <?= $grants ?>
<?php else: ?>
  <form class="field-edit" data-field-edit="<?= $template->escape($action) ?>"
    data-csrf="<?= $template->escape($csrf) ?>">
<?php if ($public): ?>
    <label class="field-toggle"><input type="checkbox" role="switch" name="feed"
      value="1"<?= $column->feed ? ' checked'
      : '' ?>><span><?= $template->escape($words->get('field.feed')) ?></span></label>
<?php endif ?>
    <p class="grant-error" role="alert" data-field-error></p>
  </form>
  <?= $grants ?>
<?php if ($entry->removable): ?>
  <form class="field-remove" method="post" action="<?= $template->escape($action) ?>/remove">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <input type="hidden" name="back" value="<?= $template->escape(http_build_query($parameters)) ?>">
    <button type="button" class="button-quiet is-danger"
      data-field-confirm><?= $template->escape($words->get('field.remove', ['label' => $entry->name,
      'collection' => $collection->name])) ?></button>
    <div data-field-confirmed hidden>
      <p class="popover-hint"><?= $template->escape($words->get(count($entry->keys) > 1 ? 'field.remove_whole'
        : 'field.remove_keeps', ['n' => (string) count($entry->keys)])) ?></p>
<?php if ($breaks !== []): ?>
      <p class="popover-hint is-warning"><?= $template->escape($words->get('field.remove_breaks',
        ['names' => implode(', ', array_keys($breaks))])) ?></p>
<?php endif ?>
      <button type="submit" class="button-danger"><?= $template->escape($words->get('field.remove_now')) ?></button>
    </div>
  </form>
<?php endif ?>
<?php endif ?>
</template>
