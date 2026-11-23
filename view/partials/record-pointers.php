<?php

declare(strict_types=1);

/* The record screen's Points here: what refers to the record, read off the edges
   and never kept, each a link where the reader may open what points. It says what
   stands and never whether a delete is harmless: an account holding the person
   refuses it whatever points here (`partials/record-account`), and a retention
   duty is no edge. A work's translations are one row with a link per language,
   and the count is of records, since each is one more a delete leaves pointing
   nowhere; a labelled thing's words in other languages are links drawn round, and
   a row names its type in the singular, each being one record, as its glyph's
   title and in words a screen reader speaks. Past ten of a type and field, one
   row says how many more and opens them in their collection (`Admin\Pointers`).
   Nothing pointing draws no card. Handed `$pointers`, `$pointing`, their count,
   and `$words`. */

if (!isset($template)) {
  return;
}
if ($pointers === []) {
  return;
}
?>
  <section class="record-card side-card" aria-labelledby="pointers-title">
    <h2 class="side-card-title" id="pointers-title"><?= $template->escape($words->get('record.pointers')) ?></h2>
    <p class="side-card-sub"><?= $template->escape($words->get('record.pointers_count',
      ['n' => $words->number($pointing)])) ?></p>
    <ul class="record-rows is-aligned">
<?php foreach ($pointers as $pointer):
  if ($pointer->more > 0):
    $inner = sprintf('<span class="record-row-glyph" title="%s">%s</span><span class="record-row-name is-more">%s'
      . '</span>', $template->escape($pointer->collection),
      $template->render('partials/glyph', ['name' => $pointer->glyph]), $template->escape($pointer->label)) ?>
      <li><?= $pointer->href === null ? '<div class="record-row">' . $inner . '</div>' : '<a class="record-row" href="'
        . $template->escape($pointer->href) . '">' . $inner . '</a>' ?></li>
<?php
    continue;
  endif;
  $languages = '';
  foreach ($pointer->locales as $tag => $href) {
    $current = $href === $pointer->href;
    $languages .= sprintf('<a class="locale-dot%s%s" href="%s"%s>%s</a>', $pointer->worded ? ' is-words' : '',
      $current ? ' is-current' : '', $template->escape($href), $current ? ' aria-current="true"' : '',
      $template->escape($tag));
  }
  $named = sprintf($languages === '' ? '<span class="record-row-name">%2$s</span>'
    : '<a class="record-row-name" href="%s">%s</a>', $template->escape((string) $pointer->href),
    $template->escape($pointer->label));
  $status = $template->escape($words->get('status.' . $pointer->state));
  $state = sprintf('<span class="state-dot state-%s" title="%s"></span><span class="visually-hidden">%s, %s</span>',
    $template->escape($pointer->state), $status, $status, $template->escape($pointer->collection));
  $inner = sprintf('<span class="record-row-glyph" title="%s">%s</span>%s<span class="record-row-meta"><span'
    . ' class="record-row-mono">%s</span></span>%s<span class="record-row-state">%s</span>',
    $template->escape($pointer->collection), $template->render('partials/glyph', ['name' => $pointer->glyph]),
    $named, $template->escape($pointer->field), $languages === '' ? ''
    : '<span class="record-row-acts"><span class="record-row-locales">' . $languages . '</span></span>', $state) ?>
<?php if ($languages !== ''): ?>
      <li class="record-row is-linked"><?= $inner ?></li>
<?php else: ?>
      <li><?= $pointer->href === null ? '<div class="record-row">' . $inner . '</div>' : '<a class="record-row" href="'
        . $template->escape($pointer->href) . '">' . $inner . '</a>' ?></li>
<?php endif ?>
<?php endforeach ?>
    </ul>
  </section>
