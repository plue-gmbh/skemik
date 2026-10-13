<?php

declare(strict_types=1);

/* The record screen's Points here: what refers to the record, read off the edges
   and never kept, each a link where the reader may open what points. It says
   what stands and never whether a delete is harmless: an account holding the
   person refuses it whatever points here, and a retention duty is no edge. */

if (!isset($template)) {
  return;
}
?>
  <section class="record-card side-card" aria-labelledby="pointers-title">
    <h2 class="side-card-title" id="pointers-title"><?= $template->escape($words->get('record.pointers')) ?></h2>
<?php if ($held !== null): ?>
    <p class="side-card-sub"><?= $template->escape($words->get('record.held', ['email' => $held->email])) ?></p>
<?php endif ?>
<?php if ($pointers === []): ?>
    <p class="side-card-sub"><?= $template->escape($words->get('record.no_pointers')) ?></p>
<?php else: ?>
    <p class="side-card-sub"><?= $template->escape($words->get('record.pointers_count',
      ['n' => (string) count($pointers)])) ?></p>
    <ul class="record-rows">
<?php foreach ($pointers as $pointer):
  $inner = sprintf('<span class="record-row-glyph">%s</span>'
    . '<span class="record-row-name">%s</span>'
    . '<span class="state-dot state-%s"></span><span class="visually-hidden">%s</span>'
    . '<span class="record-row-note">%s</span><span class="record-row-mono">%s</span>',
    $template->render('partials/glyph', ['name' => $pointer->glyph]), $template->escape($pointer->label),
    $template->escape($pointer->state),
    $template->escape($words->get('status.' . $pointer->state)), $template->escape($pointer->collection),
    $template->escape($pointer->field)) ?>
      <li><?= $pointer->href === null ? '<div class="record-row">' . $inner . '</div>' : '<a class="record-row" href="'
        . $template->escape($pointer->href) . '">' . $inner . '</a>' ?></li>
<?php endforeach ?>
    </ul>
<?php endif ?>
  </section>
