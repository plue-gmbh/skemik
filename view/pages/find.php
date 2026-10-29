<?php

declare(strict_types=1);

/* The header's search answered (`Admin\FindController`): per collection the
   records found, best first, each a link to its screen with its slug and state,
   and a link on to the rest on the collection's own screen under the same `?q=`;
   then for whoever reads the log what it holds of the question. The question is
   the header field's, so the page repeats it only in its count. */

if (!isset($template)) {
  return;
}
$row = fn (Admin\Found $found): string => sprintf('<li><a class="record-row" href="%s"><span'
  . ' class="record-row-glyph">%s</span><span class="record-row-name">%s</span>%s%s</a></li>',
  $template->escape($found->href), $template->render('partials/glyph', ['name' => $found->glyph]),
  $template->escape($found->label), $found->note === '' ? ''
  : '<span class="record-row-mono">' . $template->escape($found->note) . '</span>', $found->state === null ? ''
  : '<span class="state-dot state-' . $template->escape($found->state) . '"></span><span class="visually-hidden">'
  . $template->escape($words->get('status.' . $found->state)) . '</span>');
?>
<div class="page" data-find>
  <header class="page-head">
    <h1><?= $template->escape($words->get('find.title')) ?></h1>
    <p class="page-shape" role="status"><?= $template->escape($words->get(match (true) {
      $asked === '' => 'find.explain',
      $total + count($lines) === 0 => 'find.none',
      default => 'find.count',
    }, ['q' => $asked, 'n' => (string) $total])) ?></p>
  </header>
<?php if ($asked === ''): ?>
  <p class="sheet-empty"><?= $template->escape($words->get('find.empty')) ?></p>
<?php endif ?>
<?php foreach ($groups as $group): ?>
  <h2 class="find-group"><?= $template->escape($group->name) ?></h2>
  <ul class="record-rows">
<?php foreach ($group->shown as $found): ?>
    <?= $row($found) ?>
<?php endforeach ?>
  </ul>
<?php if ($group->more > 0): ?>
  <p class="find-more"><a href="<?= $template->escape($group->href) ?>"><?= $template->escape($words->get(
    $group->capped ? 'find.more_capped' : 'find.more', ['n' => (string) $group->more, 'collection' => $group->name]))
    ?></a></p>
<?php endif ?>
<?php endforeach ?>
<?php if ($lines !== []): ?>
  <h2 class="find-group"><?= $template->escape($words->get('find.logs')) ?></h2>
  <ul class="record-rows">
<?php foreach ($lines as $found): ?>
    <?= $row($found) ?>
<?php endforeach ?>
  </ul>
<?php endif ?>
</div>
