<?php

declare(strict_types=1);

/* One collection's trash (`Admin\TrashController`): what was deleted, last first,
   each with the way back, and ticks with a bar over them (`scripts/trash.js`) that
   restores the lot or purges it. Deleting for good is the owner's and a different
   word on a different screen than Delete, confirmed by a dialog that counts the
   references leaving with the record and, for a person who changed records,
   offers to take their name out of the log, which a purge alone leaves there.
   The lot's dialog counts over every ticked row, so each row carries its counts;
   the retention warning is the type's, and a trash holds one type. */

if (!isset($template)) {
  return;
}
$address = '/c/' . $collection->slug->value;
$held = $rows !== [] && $rows[0]->held;
?>
<div class="page" data-trash>
  <header class="collection-head">
    <div class="collection-title">
      <h1><?= $template->escape($title) ?></h1>
      <p class="page-shape"><?= $template->escape($words->get($purges ? 'trash.explain_owner' : 'trash.explain')) ?></p>
      <a class="tool-button is-wide trash-back"
        href="<?= $template->escape($address) ?>"><?= $template->escape($words->get('trash.back',
        ['collection' => $collection->name])) ?></a>
    </div>
  </header>
<?php if ($purged !== null): ?>
  <p class="page-note is-done" role="status"><?= $template->escape($purged) ?></p>
<?php endif ?>
<?php if ($rows === []): ?>
  <p class="sheet-empty"><?= $template->escape($words->get('trash.empty')) ?></p>
<?php endif ?>
<?php if ($rows !== []): ?>
  <div class="ticked-bar" role="toolbar" aria-label="<?= $template->escape($words->get('sheet.ticked_actions')) ?>"
    data-ticked hidden>
    <span class="ticked-count" data-ticked-count data-short="<?= $template->escape($words->get('sheet.ticked_count',
      ['n' => '{n}'])) ?>" data-long="<?= $template->escape($words->get('sheet.ticked', ['n' => '{n}'])) ?>"><span
      class="ticked-short" aria-hidden="true"></span><span class="ticked-long"></span></span>
    <form method="post" action="<?= $template->escape($address) ?>/restore">
      <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
      <input type="hidden" name="ids" data-ticked-ids>
      <button type="submit" class="button-quiet"><?= $template->escape($words->get('record.restore')) ?></button>
    </form>
<?php if ($purges): ?>
    <button type="button" class="tool-button is-danger" aria-haspopup="dialog" data-dialog="purge-ticked"
      title="<?= $template->escape($words->get('purge.confirm')) ?>"><?= $template->render('partials/icon',
      ['name' => 'trash']) ?><span
      class="visually-hidden"><?= $template->escape($words->get('purge.confirm')) ?></span></button>
<?php endif ?>
    <button type="button" class="tool-button" data-ticked-clear
      title="<?= $template->escape($words->get('sheet.untick')) ?>"><?= $template->render('partials/icon',
      ['name' => 'close']) ?><span
      class="visually-hidden"><?= $template->escape($words->get('sheet.untick')) ?></span></button>
  </div>
  <label class="item-tick-all"><input type="checkbox"
    data-tick-all><?= $template->escape($words->get('sheet.tick_all')) ?></label>
<?php endif ?>
  <ul class="record-rows">
<?php foreach ($rows as $row): $id = $row->entity->id->value ?>
    <li class="record-row" data-record="<?= $template->escape($id) ?>" data-pointing="<?= $row->pointing
      ?>"<?= $row->acted ? ' data-acted' : '' ?>>
      <span class="item-tick"><input type="checkbox" data-tick
        aria-label="<?= $template->escape($words->get('sheet.tick', ['label' => $row->label])) ?>"></span>
      <span class="record-row-name"><?= $template->escape($row->label) ?></span>
      <span class="record-row-note"><?= $template->escape($words->get('trash.since', ['when' => $row->since])) ?></span>
      <span class="record-row-mono">/<?= $template->escape($row->entity->slug->value) ?></span>
      <form method="post" action="<?= $template->escape($address . '/' . $id) ?>/restore">
        <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
        <button type="submit" class="button-quiet"><?= $template->escape($words->get('record.restore')) ?><span
          class="visually-hidden"> – <?= $template->escape($row->label) ?></span></button>
<?php if ($purges): ?>
        <button type="button" class="button-quiet is-danger" aria-haspopup="dialog"
          data-dialog="purge-<?= $template->escape($id) ?>"><?= $template->escape($words->get('purge.confirm')) ?><span
          class="visually-hidden"> – <?= $template->escape($row->label) ?></span></button>
<?php endif ?>
      </form>
<?php if ($purges): ?>
      <dialog class="dialog" id="purge-<?= $template->escape($id) ?>"
        aria-labelledby="purge-<?= $template->escape($id) ?>-title">
        <form method="post" action="<?= $template->escape($address . '/' . $id) ?>/purge">
          <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
          <h2 id="purge-<?= $template->escape($id) ?>-title"><?= $template->escape($words->get('purge.question',
            ['label' => $row->label])) ?></h2>
          <p><?= $template->escape($row->pointing === 0 ? $words->get('purge.nothing') : $words->get('purge.pointing',
            ['n' => (string) $row->pointing])) ?></p>
<?php if ($row->held): ?>
          <p><strong><?= $template->escape($words->get('purge.held')) ?></strong></p>
<?php endif ?>
<?php if ($row->acted): ?>
          <p><?= $template->escape($words->get('purge.log')) ?></p>
          <p><label><input type="checkbox" name="redact"
            value="1"> <?= $template->escape($words->get('purge.redact')) ?></label></p>
<?php endif ?>
          <div class="dialog-actions">
            <button type="button" class="button-quiet"
              data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
            <button type="submit" class="button-danger"><?= $template->escape($words->get('purge.confirm')) ?></button>
          </div>
        </form>
      </dialog>
<?php endif ?>
    </li>
<?php endforeach ?>
  </ul>
<?php if ($purges && $rows !== []): ?>
  <dialog class="dialog" id="purge-ticked" aria-labelledby="purge-ticked-title">
    <form method="post" action="<?= $template->escape($address) ?>/purge">
      <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
      <input type="hidden" name="ids" data-ticked-ids>
      <h2 id="purge-ticked-title" data-text="<?= $template->escape($words->get('purge.question_many',
        ['n' => '{n}'])) ?>"></h2>
      <p data-pointing data-none="<?= $template->escape($words->get('purge.nothing_many')) ?>"
        data-text="<?= $template->escape($words->get('purge.pointing_many', ['n' => '{n}'])) ?>"></p>
<?php if ($held): ?>
      <p><strong><?= $template->escape($words->get('purge.held')) ?></strong></p>
<?php endif ?>
      <div data-acted hidden>
        <p><?= $template->escape($words->get('purge.log_many')) ?></p>
        <p><label><input type="checkbox" name="redact"
          value="1"> <?= $template->escape($words->get('purge.redact_many')) ?></label></p>
      </div>
      <div class="dialog-actions">
        <button type="button" class="button-quiet"
          data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
        <button type="submit" class="button-danger"><?= $template->escape($words->get('purge.confirm')) ?></button>
      </div>
    </form>
  </dialog>
<?php endif ?>
  <?= $template->render('partials/sheet-pages', ['words' => $words, 'page' => $page,
    'perPage' => Storage\Query::PER_PAGE, 'total' => $total, 'shown' => count($rows), 'parameters' => []]) ?>
</div>
