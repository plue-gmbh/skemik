<?php

declare(strict_types=1);

/* The error log screen (`Admin\ErrorLogController`): the incident search first,
   since a person quoting six characters is why anybody opens it, then the
   entries newest first, each one line, its incident, when, the status and the
   address asked for, and what failed, with where in the code and the trace
   folded under it for whoever the owner forwards it to. */

if (!isset($template)) {
  return;
}
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('errors.title')) ?></h1>
    <p class="page-shape"><?= $template->escape($words->get('errors.explain', ['n' => (string) $shown])) ?></p>
  </header>
  <form class="error-search" method="get" action="/settings/errors" role="search">
    <input class="query-search" type="search" name="incident" value="<?= $template->escape($incident) ?>"
      placeholder="<?= $template->escape($words->get('errors.search')) ?>"
      aria-label="<?= $template->escape($words->get('errors.search')) ?>" autocomplete="off" spellcheck="false">
    <button type="submit" class="tool-button is-wide"><?= $template->escape($words->get('errors.find')) ?></button>
<?php if ($incident !== ''): ?>
    <a class="button-quiet" href="/settings/errors"><?= $template->escape($words->get('errors.all')) ?></a>
<?php endif ?>
  </form>
<?php if ($incident !== ''): ?>
  <p class="page-note" role="status"><?= $template->escape($words->get($entries === [] ? 'errors.not_found'
    : 'errors.found', ['incident' => $incident, 'n' => (string) count($entries)])) ?></p>
<?php elseif ($entries === []): ?>
  <p class="sheet-empty"><?= $template->escape($words->get('errors.empty')) ?></p>
<?php endif ?>
  <ul class="record-rows">
<?php foreach ($entries as $entry): ?>
    <li class="record-row">
      <span class="record-row-name error-incident"><?= $template->escape($entry->incident) ?></span>
      <span class="record-row-note"><?= $template->escape($when($entry->time)) ?> · <?= $template->escape((string)
          $entry->status) ?> · <?= $template->escape($entry->route) ?></span>
      <span class="record-row-kept"><?= $template->escape($entry->class) ?>: <?= $template->escape($entry->message)
          ?></span>
<?php if ($entry->place !== ''): ?>
      <details class="error-trace">
        <summary><?= $template->escape($words->get('errors.where')) ?><span
          class="visually-hidden"> – <?= $template->escape($entry->incident) ?></span></summary>
        <pre><?= $template->escape($entry->place . ($entry->trace === '' ? '' : "\n" . $entry->trace)) ?></pre>
      </details>
<?php endif ?>
    </li>
<?php endforeach ?>
  </ul>
</div>
