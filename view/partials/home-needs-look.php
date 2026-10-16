<?php

declare(strict_types=1);

/* Needs a look (`Admin\NeedsLook`), for everybody: what somebody should go and
   mend or take up, an order a till wrote and a reservation a booking service
   wrote among it, found by the card rather than by opening collection after
   collection or by an integration stopping on its date. Each line opens where
   the mending starts, a filtered collection where a filter names the records and
   the first record where none does; a daily snapshot that failed stands here,
   since one failing for three weeks must be a line and not an absence. None is
   drawn at nothing, and with none the card says so. */

if (!isset($template)) {
  return;
}
if ($findings === []) { ?>
      <p class="home-empty"><?= $template->escape($words->get('look.nothing')) ?></p>
<?php } else { ?>
      <ul class="record-rows">
<?php foreach ($findings as $each) { ?>
<?php $element = $each->href === null ? 'span' : 'a'; ?>
        <li><<?= $element ?> class="record-row"<?= $each->href === null ? '' : ' href="'
          . $template->escape($each->href) . '"' ?>><span
          class="record-row-glyph"><?= $template->render('partials/glyph', ['name' => $each->glyph]) ?></span><span
          class="record-row-name"><?= $template->escape($words->get('look.' . ($each->count === 1 ? 'one' : 'many')
          . '.' . $each->look->value,
          ['n' => (string) $each->count])) ?></span><?php if ($each->collection !== '') { ?><span
          class="record-row-note"><?= $template->escape($each->collection) ?><?= $each->others > 0 ? ' +'
          . $each->others : '' ?></span><?php } ?></<?= $element ?>></li>
<?php } ?>
      </ul>
<?php } ?>
