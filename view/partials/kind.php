<?php

declare(strict_types=1);

/* A field kind's glyph in the kind's colour, drawn from `partials/glyphs`:
   decoration beside a label that already says what the field is, so hidden from
   a screen reader. */

if (!isset($template)) {
  return;
}
?>
<span class="kind kind-<?= $template->escape($kind->value) ?>"><?= trim($template->render('partials/glyph',
  ['name' => 'kind-' . $kind->value])) ?></span>
