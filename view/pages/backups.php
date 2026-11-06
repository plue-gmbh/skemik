<?php

declare(strict_types=1);

/* The backups screen (`Update\BackupController`): what a snapshot is and how
   many are kept, what the last daily run left where it went wrong, the button
   for one now beside the switch for the daily ones, which `config.php` keeps and
   which is disabled where PHP may not write it, and the snapshots newest first,
   each with when, what for, its size, its path, its download, and its restore
   behind a dialog saying what a restore is. A snapshot too large for the
   browser keeps its download and says to fetch the folder over SFTP.
   Handed `$rows`, `$report`, `$daily`, `$notice` and `$refusal`, each a sentence or null, `$switchable`,
   `$csrf` and `$words`. */

if (!isset($template)) {
  return;
}
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('backups.title')) ?></h1>
    <p class="page-shape"><?= $template->escape($words->get('backups.explain')) ?></p>
  </header>
<?php if ($notice !== null): ?>
  <p class="page-note is-done" role="status"><?= $template->escape($notice) ?></p>
<?php endif ?>
<?php if ($refusal !== null): ?>
  <p class="page-note is-refused" role="alert"><?= $template->escape($refusal) ?></p>
<?php endif ?>
<?php if ($report?->failed !== null): ?>
  <p class="page-note is-refused"><?= $template->escape($words->get('backups.failed', ['day' => $report->day,
    'reason' => $report->failed])) ?></p>
<?php elseif ($report?->shrunk): ?>
  <p class="page-note is-refused"><?= $template->escape($words->get('backups.shrunk', ['day' => $report->day])) ?></p>
<?php endif ?>
  <div class="settings-actions">
    <form method="post" action="/settings/backups">
      <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
      <button type="submit"
        class="button-secondary"><?= $template->escape($words->get('backups.take')) ?></button>
    </form>
<?= $template->render('partials/setting-switch', ['action' => '/settings/backups/daily', 'name' => 'daily',
  'value' => $daily ? 'off' : 'on', 'on' => $daily, 'label' => $words->get('backups.daily'), 'csrf' => $csrf,
  'unwritable' => $switchable ? null : 'backups-unwritable']) ?>
  </div>
<?php if (!$switchable): ?>
  <p class="popover-hint" id="backups-unwritable"><?= $template->escape($words->get('general.unwritable')) ?></p>
<?php endif ?>
<?php if ($rows === []): ?>
  <p class="sheet-empty"><?= $template->escape($words->get('backups.empty')) ?></p>
<?php endif ?>
  <ul class="record-rows">
<?php foreach ($rows as $row):
  $name = $row->snapshot->name;
  $when = $template->escape($row->when) ?>
    <li class="record-row">
      <span class="record-row-name"><?= $when ?></span>
      <span class="record-row-note"><?= $template->escape($words->get('backups.for.' . $row->snapshot->occasion->value))
        ?> · <?= $template->escape($words->get('backups.size', ['mb' => $row->size])) ?></span>
      <span class="record-row-mono"
        title="<?= $template->escape($row->snapshot->path) ?>"><?= $template->escape($row->snapshot->path) ?></span>
      <form method="post" action="/settings/backups/<?= $template->escape($name) ?>/restore">
        <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
        <button type="submit" class="tool-button" data-download
          title="<?= $template->escape($words->get('backups.download')) ?>"
          formaction="/settings/backups/<?= $template->escape($name) ?>/download"><?= $template->render('partials/icon',
            ['name' => 'export']) ?><span class="visually-hidden"><?= $template->escape(
            $words->get('backups.download')) ?> – <?= $when ?></span></button>
        <button type="button" class="tool-button is-danger" aria-haspopup="dialog"
          title="<?= $template->escape($words->get('backups.restore')) ?>"
          data-dialog="restore-<?= $template->escape($name) ?>"><?= $template->render('partials/icon',
            ['name' => 'restore']) ?><span class="visually-hidden"><?= $template->escape(
            $words->get('backups.restore')) ?> – <?= $when ?></span></button>
      </form>
      <dialog class="dialog" id="restore-<?= $template->escape($name) ?>"
        aria-labelledby="restore-<?= $template->escape($name) ?>-title">
        <?= $template->render('partials/live-region') ?>
        <form method="post" action="/settings/backups/<?= $template->escape($name) ?>/restore">
          <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
          <h2 id="restore-<?= $template->escape($name) ?>-title"><?= $template->escape(
            $words->get('backups.restore_question', ['when' => $row->when])) ?></h2>
          <p><?= $template->escape($words->get('backups.restore_explain')) ?></p>
          <div class="dialog-actions">
            <button type="button" class="button-quiet"
              data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
            <button type="submit"
              class="button-danger"><?= $template->escape($words->get('backups.restore')) ?></button>
          </div>
        </form>
      </dialog>
<?php if ($row->large): ?>
      <span class="record-row-kept"><?= $template->escape($words->get('backups.large')) ?></span>
<?php endif ?>
    </li>
<?php endforeach ?>
  </ul>
</div>
