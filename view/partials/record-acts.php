<?php

declare(strict_types=1);

/* The record screen's acts on the whole record, for whoever writes it. A copy is
   made at once, a draft that opens for editing, and stands here alone, since
   every view opens the record it would start from. The trash is confirmed in
   `partials/delete-confirm`, and a person holding an account keeps their record,
   so the trash says why instead. A type requiring a value held once, a term its
   code, offers no copy, which would lack it. */

if (!isset($template)) {
  return;
}
$address = '/c/' . $collection->slug->value . '/' . $entity->id->value;
?>
<?php if ($copies): $copy = $template->escape($words->get('record.duplicate')) ?>
      <form method="post" action="<?= $template->escape($address) ?>/duplicate"><input type="hidden" name="_csrf"
        value="<?= $template->escape($csrf) ?>"><button type="submit" class="tool-button"
        title="<?= $copy ?>"><?= $template->render('partials/icon', ['name' => 'copy']) ?><span
        class="visually-hidden"><?= $copy ?></span></button></form>
<?php endif ?>
<?php if ($held !== null): ?>
      <button type="button" class="tool-button" aria-disabled="true"
        title="<?= $template->escape($words->get('record.held', ['email' => $held->email])) ?>"
        data-toast="<?= $template->escape($words->get('record.held',
        ['email' => $held->email])) ?>"><?= $template->render('partials/icon', ['name' => 'trash']) ?><span
        class="visually-hidden"><?= $template->escape($words->get('record.held',
        ['email' => $held->email])) ?></span></button>
<?php else: ?>
      <button type="button" class="tool-button" title="<?= $template->escape($words->get('delete.confirm')) ?>"
        data-dialog="delete-<?= $template->escape($entity->id->value) ?>"><?= $template->render('partials/icon',
        ['name' => 'trash']) ?><span
        class="visually-hidden"><?= $template->escape($words->get('delete.confirm')) ?></span></button>
<?php endif ?>
