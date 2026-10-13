<?php

declare(strict_types=1);

/* The export screen (`Admin\ExportController`): what a dump is, the button
   above as on the backups screen, or the sentence why it cannot be taken, then
   which types come out complete and which as the public reads them, how many
   records and files it will hold and about how large it is. The numbers carry
   their name as data, so a check can read them where the words change with the
   language. */

if (!isset($template)) {
  return;
}
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('export.title')) ?></h1>
    <p class="page-shape"><?= $template->escape($words->get('export.explain')) ?></p>
  </header>
<?php if ($notice !== null): ?>
  <p class="page-note is-refused" role="status"><?= $template->escape($notice) ?></p>
<?php endif ?>
<?php if ($unfit !== null): ?>
  <p class="page-note is-refused"><?= $template->escape($unfit) ?></p>
<?php else: ?>
  <form method="post" action="/export">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <button type="submit" class="button-secondary trash-back"
      data-download><?= $template->escape($words->get('export.take')) ?></button>
  </form>
<?php endif ?>
  <ul class="record-rows">
<?php if ($complete !== []): ?>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('export.complete')) ?></span>
      <span class="record-row-note"><?= $template->escape(implode(', ', $complete)) ?></span>
    </li>
<?php endif ?>
<?php if ($public !== []): ?>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('export.public')) ?></span>
      <span class="record-row-note"><?= $template->escape(implode(', ', $public)) ?></span>
    </li>
<?php endif ?>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('export.records')) ?></span>
      <span class="record-row-note" data-export="records"><?= $size->records ?></span>
    </li>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('export.files')) ?></span>
      <span class="record-row-note" data-export="files"><?= $size->files ?></span>
    </li>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('export.size')) ?></span>
      <span class="record-row-note" data-export="size"><?= $template->escape($words->get('export.about',
        ['size' => $about])) ?></span>
    </li>
  </ul>
</div>
