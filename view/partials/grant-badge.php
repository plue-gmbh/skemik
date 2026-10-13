<?php

declare(strict_types=1);

/* The collection's grant badge, and a record's: its mark, violet while a live
   grant holds, and the popover of `partials/grant-panel`. A field's grants are
   in its column's field popover instead (`partials/field-popover`). */

if (!isset($template)) {
  return;
}
$id = 'grant-' . ($badge->field ?? 'collection');
$names = $badge->names();
$title = $names === '' ? $words->get($badge->subject === null ? 'grant.give' : 'grant.give_record',
  ['anchor' => $badge->title]) : $words->get('grant.here', ['names' => $names]);
?>
<button type="button" class="grant-badge<?= $badge->reviews === [] ? '' : ' is-live' ?>" aria-haspopup="dialog"
  title="<?= $template->escape($title) ?>" data-grant="<?= $template->escape($id) ?>"><?= $template->render(
  'partials/glyph', ['name' => 'key']) ?><span class="visually-hidden"><?= $template->escape($title) ?></span></button>
<template id="<?= $template->escape($id) ?>">
<?= $template->render('partials/grant-panel', ['words' => $words, 'badge' => $badge, 'collection' => $collection,
  'csrf' => $csrf, 'back' => $back, 'until' => $until]) ?>
</template>
