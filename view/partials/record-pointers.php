<?php

declare(strict_types=1);

/* The record screen's Points here: what refers to the record, read off the edges
   and never kept, each a link where the reader may open what points. It says
   what stands and never whether a delete is harmless: an account holding the
   person refuses it whatever points here, and a retention duty is no edge. A
   work's translations are one row with a link per language, and the count is
   of records, since each is one more that a delete leaves pointing nowhere. */

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
      ['n' => (string) $pointing])) ?></p>
    <ul class="record-rows">
<?php foreach ($pointers as $pointer):
  $languages = '';
  foreach ($pointer->locales as $tag => $href) {
    $current = $href === $pointer->href;
    $languages .= sprintf('<a class="locale-dot%s" href="%s"%s>%s</a>', $current ? ' is-current' : '',
      $template->escape($href), $current ? ' aria-current="true"' : '', $template->escape($tag));
  }
  $named = sprintf($languages === '' ? '<span class="record-row-name">%2$s</span>'
    : '<a class="record-row-name" href="%s">%s</a>', $template->escape((string) $pointer->href),
    $template->escape($pointer->label));
  $state = sprintf('<span class="state-dot state-%s"></span><span class="visually-hidden">%s</span>'
    . '<span class="record-row-note">%s</span>', $template->escape($pointer->state),
    $template->escape($words->get('status.' . $pointer->state)), $template->escape($pointer->collection));
  $inner = sprintf('<span class="record-row-glyph">%s</span>%s%s<span class="record-row-mono">%s</span>',
    $template->render('partials/glyph', ['name' => $pointer->glyph]), $named, $languages === '' ? $state
    : '<span class="record-row-locales">' . $languages . '</span><span class="record-row-state">' . $state . '</span>',
    $template->escape($pointer->field)) ?>
<?php if ($languages !== ''): ?>
      <li class="record-row is-linked"><?= $inner ?></li>
<?php else: ?>
      <li><?= $pointer->href === null ? '<div class="record-row">' . $inner . '</div>' : '<a class="record-row" href="'
        . $template->escape($pointer->href) . '">' . $inner . '</a>' ?></li>
<?php endif ?>
<?php endforeach ?>
    </ul>
<?php endif ?>
  </section>
