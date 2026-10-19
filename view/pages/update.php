<?php

declare(strict_types=1);

/* The update screen (`Update\UpdateController`): the running version, whether
   releases are installed automatically, early, or declined in config.php, when the
   install last checked or that it could not, the last release installed
   automatically and whether it was a security release, the release that cannot be
   installed with the reason and the copy by hand named, and last the key
   downloads are verified against, which a reader looks up and never acts on.
   The check is red once a day passed without one (`UpdateCheck::stale`). */

if (!isset($template)) {
  return;
}
?>
<div class="page update-page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('update.title')) ?></h1>
    <p class="page-shape"><?= $template->escape($words->get(match (true) {
      !$enabled => 'update.declined',
      $early => 'update.early',
      default => 'update.explain',
    }, ['version' => $version])) ?></p>
  </header>
<?php if ($report->refused !== null): ?>
  <p class="page-note is-refused"><?= $template->escape($words->get($report->again ? 'update.refused_again'
    : 'update.refused', ['version' => $report->refused, 'reason' => $report->reason ?? ''])) ?></p>
<?php endif ?>
  <ul class="record-rows">
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('update.check')) ?></span>
<?php [$check, $mark] = match (true) {
  $report->checked === null => [$words->get('update.unchecked'), ''],
  $report->unreachable !== null => [$words->get('update.unreachable', ['reason' => $report->unreachable]),
    ' is-failing'],
  $report->offered !== null && $report->offered !== $report->installed && $report->offered !== $report->refused
    => [$words->get($enabled ? 'update.offered' : 'update.available', ['version' => $report->offered]), ''],
  default => [$words->get('update.checked'), $stale ? ' is-failing' : ' is-ok'],
} ?>
      <span class="record-row-note<?= $mark ?>"><?= $template->escape($check) ?></span>
<?php if ($report->checked !== null): ?>
      <span class="record-row-mono"><?= $template->escape($checked) ?></span>
<?php endif ?>
    </li>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('update.installed')) ?></span>
      <span class="record-row-note<?= $report->installed === null ? '' : ' is-ok' ?>"><?= $template->escape(
        $report->installed === null ? $words->get('update.none')
        : $words->get($report->security ? 'update.release_security' : 'update.release',
          ['version' => $report->installed])) ?></span>
<?php if ($report->installed !== null): ?>
      <span class="record-row-mono"><?= $template->escape($installedAt) ?></span>
<?php endif ?>
    </li>
    <li class="record-row">
      <span class="record-row-name"><?= $template->escape($words->get('update.key')) ?></span>
      <span class="record-row-note<?= $key === '' ? '' : ' is-ok' ?>"><?= $template->escape($words->get($key === ''
        ? 'update.key_none' : 'update.key_set')) ?></span>
<?php if ($key !== ''): ?>
      <span class="record-row-mono"><?= $template->escape($key) ?></span>
<?php endif ?>
    </li>
  </ul>
</div>
