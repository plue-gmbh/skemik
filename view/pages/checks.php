<?php

declare(strict_types=1);

/* The holdings check's screen (`Admin\ChecksController`): when the report was
   made and how a record leaves it, then a line per finding, the house's own
   first, each naming its record and collection and opening the record, or its
   trash, with the field and what does not fit beneath. */

if (!isset($template)) {
  return;
}
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('checks.title')) ?></h1>
    <p class="page-shape"><?= $template->escape($report === null ? $words->get('checks.unchecked')
      : $words->get('checks.explain', ['at' => $at, 'version' => $report->version])) ?></p>
  </header>
<?php if ($report !== null && $count === 0): ?>
  <p class="sheet-empty"><?= $template->escape($words->get('checks.empty')) ?></p>
<?php elseif ($count > $shown): ?>
  <p class="page-note"><?= $template->escape($words->get('checks.shown', ['n' => (string) $shown,
    'all' => (string) $count])) ?></p>
<?php endif ?>
  <ul class="record-rows">
<?php foreach ($entries as $entry): $element = $entry->href === null ? 'span' : 'a' ?>
    <li><<?= $element ?> class="record-row"<?= $entry->href === null ? '' : ' href="' . $template->escape($entry->href)
      . '"' ?>>
      <span class="record-row-glyph"><?= $template->render('partials/glyph', ['name' => $entry->glyph]) ?></span>
      <span class="record-row-name"><?= $template->escape($entry->name) ?></span>
<?php if ($entry->collection !== ''): ?>
      <span class="record-row-note"><?= $template->escape($entry->collection) ?></span>
<?php endif ?>
      <span class="record-row-kept"><?= $template->escape($entry->sentence) ?></span>
    </<?= $element ?>></li>
<?php endforeach ?>
  </ul>
</div>
