<?php

declare(strict_types=1);

/* Deleting, from the record screen: a confirmation that names what points at the
   record first — Delete “Workshop report”? References: 3, or Nothing points at
   it — counted under the reader's scope by the Points here
   card's rule (`Admin\Showing`). A link may fall back to another translation
   afterwards, so the sentence says only that none shows this one. It is the trash,
   immediate rather than pending, and the collection screen it returns to
   offers Restore at once, as its trash does afterwards. A view deletes by its
   ticks and never per record, so this dialog is the record screen's alone. */

if (!isset($template)) {
  return;
}
?>
<dialog class="dialog" id="delete-<?= $template->escape($row->entity->id->value) ?>"
  aria-labelledby="delete-<?= $template->escape($row->entity->id->value) ?>-title">
  <form method="post"
    action="/c/<?= $template->escape($collection->slug->value) ?>/<?= $template->escape($row->entity->id->value)
      ?>/delete">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <input type="hidden" name="back" value="<?= $template->escape($query) ?>">
    <h2 id="delete-<?= $template->escape($row->entity->id->value) ?>-title"><?= $template->escape(
        $words->get('delete.question', ['label' => $row->label])) ?></h2>
    <p><?= $template->escape($pointing === 0 ? $words->get('delete.nothing') : $words->get('delete.pointing',
      ['n' => (string) $pointing])) ?></p>
    <div class="dialog-actions">
      <button type="button" class="button-quiet"
        data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
      <button type="submit" class="button-danger"><?= $template->escape($words->get('delete.confirm')) ?></button>
    </div>
  </form>
</dialog>
