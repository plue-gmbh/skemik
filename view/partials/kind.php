<?php

declare(strict_types=1);

/* A field kind's glyph in the kind's colour, drawn from `partials/glyphs`:
   decoration beside a label that already says what the field is, so hidden from
   a screen reader, and the kind's name on hover, since the glyph alone is a
   guess. */

if (!isset($template)) {
  return;
}
?>
<span class="kind kind-<?= $template->escape($kind->value) ?>"
  title="<?= $template->escape($words->get('kind.' . $kind->value)) ?>"><?= trim($template->render('partials/glyph',
  ['name' => 'kind-' . $kind->value])) ?></span>
