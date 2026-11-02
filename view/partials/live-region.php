<?php

declare(strict_types=1);

/* The polite region `announce` speaks through (`view/scripts/app.js`): one in the frame and one first in every
   dialog, since an open modal dialog makes all outside it inert, the frame's region too, and a region made only as
   its words arrive is one some screen readers never hear. */

if (!isset($template)) {
  return;
}
?>
<div class="live-region visually-hidden" role="status" aria-live="polite" data-live-region></div>
