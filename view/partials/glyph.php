<?php

declare(strict_types=1);

/* One drawing of `partials/glyphs`, by its name: decoration beside a word that
   already says what it stands for, so hidden from a screen reader. */

if (!isset($template)) {
  return;
}
?>
<svg class="glyph" aria-hidden="true"><use href="#glyph-<?= $template->escape($name) ?>"></use></svg>
