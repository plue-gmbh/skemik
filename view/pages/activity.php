<?php

declare(strict_types=1);

/* The whole log (`Admin\ActivityController`), the owner's: a line per
   creation, save, new address, trash, restore and purge, and per change to an
   account, a key, a language or a collection's fields, newest first and
   paged back to the first, each as Last changed draws it and under who did it,
   whose name narrows the log to theirs. A record purged since is a line with no
   name, since its name went with it. Where a line keeps a former address, a
   number or an edge a purge took, it shows them, since that is what a
   redaction takes, behind a dialog saying it cannot be undone; the line stays,
   so the log still says that something changed and who changed it. The time
   is the house's, in full, since a line is read for when as often as for what. */

if (!isset($template)) {
  return;
}
$who = $by === null ? null : array_find($lines, fn (Admin\Change $line): bool => $line->by === $by)?->who;
$back = ['by' => $by, 'page' => $page > 1 ? (string) $page : null];
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('change.all')) ?></h1>
    <p class="page-shape"><?= $template->escape($words->get('change.explain')) ?></p>
  </header>
<?php if ($notice !== null): ?>
  <p class="page-note is-done" role="status"><?= $template->escape($notice) ?></p>
<?php endif ?>
<?php if ($by !== null): ?>
  <p class="page-note"><?= $template->escape($words->get('change.only',
    ['who' => $who ?? $words->get('change.unknown')])) ?> <a
    href="/changes"><?= $template->escape($words->get('change.all')) ?></a></p>
<?php endif ?>
<?php if ($lines === []): ?>
  <p class="sheet-empty"><?= $template->escape($words->get('change.none_log')) ?></p>
<?php endif ?>
  <ul class="record-rows">
<?php foreach ($lines as $index => $line):
  $public = $line->row?->reach === Profile\Reach::Public;
  $state = $line->row === null ? null : ($public ? $words->get('status.' . $line->row->state) : $line->row->own);
  $who = ($line->byKey ? '<span class="grant-key">' . $template->render('partials/glyph', ['name' => 'key'])
    . '</span>' : '') . $template->escape($line->who);
  $named = ($line->by === null || $by !== null ? $who
    : '<a href="?' . $template->escape(http_build_query(['by' => $line->by])) . '">' . $who . '</a>')
    . ($line->state === null ? '' : ' ' . $template->escape($line->state));
  $mark = $template->render('partials/glyph', ['name' => $line->glyph]);
  $dialog = 'redact-' . $index ?>
    <li class="record-row<?= $line->href === null ? ' is-missing' : '' ?>">
      <span class="record-row-glyph"><?php if ($state !== null): ?><span
        class="state-dot <?= $public ? 'state-' . $template->escape($line->row->state) : 'state-own' ?>"
        title="<?= $template->escape($state) ?>"></span><span
        class="visually-hidden"><?= $template->escape($state) ?></span><?php else: ?><?= $line->glyph === 'key'
        ? '<span class="grant-key">' . $mark . '</span>' : $mark ?><?php endif ?></span>
<?php if ($line->href === null): ?>
      <span class="record-row-name"><?= $template->escape($line->label) ?></span>
<?php else: ?>
      <a class="record-row-name" href="<?= $template->escape($line->href) ?>"><?= $template->escape($line->label) ?></a>
<?php endif ?>
      <span class="record-row-note"><?= strtr($template->escape($words->get('change.by', ['what' => "\x01",
        'who' => "\x02"])), ["\x01" => $template->escape($line->what), "\x02" => $named]) ?></span>
      <time class="record-row-mono"
        datetime="<?= $template->escape($line->when) ?>"><?= $template->escape($line->stamp) ?></time>
<?php if ($redacts && $line->activity !== null): ?>
      <button type="button" class="button-quiet is-danger" aria-haspopup="dialog"
        data-dialog="<?= $dialog ?>"><?= $template->escape($words->get('change.redact')) ?><span
        class="visually-hidden"> – <?= $template->escape($line->label) ?>, <?= $template->escape($line->stamp)
          ?></span></button>
      <dialog class="dialog" id="<?= $dialog ?>" aria-labelledby="<?= $dialog ?>-title">
        <form method="post" action="/changes/<?= $template->escape($line->activity) ?>/redact">
          <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
<?php foreach (array_filter($back) as $name => $value): ?>
          <input type="hidden" name="<?= $name ?>" value="<?= $template->escape($value) ?>">
<?php endforeach ?>
          <h2 id="<?= $dialog ?>-title"><?= $template->escape($words->get('change.redact_question')) ?></h2>
          <p class="record-row-kept"><?= $template->escape(implode(' · ', $line->kept)) ?></p>
          <p><?= $template->escape($words->get('change.redact_explain')) ?></p>
          <div class="dialog-actions">
            <button type="button" class="button-quiet"
              data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
            <button type="submit" class="button-danger"><?= $template->escape($words->get('change.redact')) ?></button>
          </div>
        </form>
      </dialog>
<?php endif ?>
<?php if ($line->kept !== []): ?>
      <span class="record-row-kept"><?= $template->escape(implode(' · ', $line->kept)) ?></span>
<?php endif ?>
    </li>
<?php endforeach ?>
  </ul>
  <?= $template->render('partials/sheet-pages', ['words' => $words, 'page' => $page, 'perPage' => $perPage,
    'total' => $total, 'shown' => count($lines), 'parameters' => ['by' => $by]]) ?>
</div>
