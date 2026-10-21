<?php

declare(strict_types=1);

/* The collection's grant badge, and a record's: its mark, violet while a live
   grant holds, and the popover of `partials/grant-panel`. A field's grants are
   in its column's field popover instead (`partials/field-popover`). Where no key
   holds and the reader may not issue, the panel would be empty, so the badge is
   the mark alone, saying that keys exist. */

if (!isset($template)) {
  return;
}
$id = 'grant-' . ($badge->field ?? 'collection');
$names = $badge->names();
$live = $badge->reviews === [] ? '' : ' is-live';
$opens = $badge->offers !== [] || $badge->reviews !== [];
$title = match (true) {
  $names !== '' => $words->get('grant.here', ['names' => $names]),
  !$opens => $words->get('grant.none'),
  default => $words->get($badge->subject === null ? 'grant.give' : 'grant.give_record', ['anchor' => $badge->title]),
};
?>
<?php if ($opens): ?>
<button type="button" class="grant-badge<?= $live ?>" aria-haspopup="dialog"
  title="<?= $template->escape($title) ?>" data-grant="<?= $template->escape($id) ?>"><?= $template->render(
  'partials/glyph', ['name' => 'key']) ?><span class="visually-hidden"><?= $template->escape($title) ?></span></button>
<template id="<?= $template->escape($id) ?>">
<?= $template->render('partials/grant-panel', ['words' => $words, 'badge' => $badge, 'collection' => $collection,
  'csrf' => $csrf, 'back' => $back, 'until' => $until]) ?>
</template>
<?php else: ?>
<span class="grant-badge<?= $live ?>" title="<?= $template->escape($title) ?>"><?= $template->render(
  'partials/glyph', ['name' => 'key']) ?><span class="visually-hidden"><?= $template->escape($title) ?></span></span>
<?php endif ?>
