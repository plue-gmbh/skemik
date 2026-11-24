<?php

declare(strict_types=1);

/* The logomark, drawn from the material's own tokens so it sits on chrome and on paper. The glyph is
   drawn to fill a favicon's square and stands a tenth smaller on the tile here, which is drawn larger.
   Handed `$size`. */

if (!isset($template)) {
  return;
}
?>
<svg class="logomark" viewBox="0 0 64 64" width="<?= (int) $size ?>" height="<?= (int) $size ?>"
  aria-hidden="true"><path class="logomark-tile"
  d="M28 0h8C59.5 0 64 4.5 64 28v8c0 23.5-4.5 28-28 28h-8C4.5 64 0 59.5 0 36v-8C0 4.5 4.5 0 28 0Z"/><g
  transform="translate(3.2 3.2) scale(.9)"><path
  d="M14 36a2 2 0 0 1-2-2V14a2 2 0 0 1 2-2h36a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H20a2 2 0 0 0-2 2v14a2 2 0 0 1-2 2Z"/><path
  d="M50 28a2 2 0 0 1 2 2v20a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h30a2 2 0 0 0 2-2V30a2 2 0 0 1 2-2Z"/><rect
  x="27" y="27" width="10" height="10" rx="2"/></g></svg>
