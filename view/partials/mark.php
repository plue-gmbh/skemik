<?php

declare(strict_types=1);

/* The logomark, drawn from the material's own tokens so it sits on chrome and on paper. */

if (!isset($template)) {
  return;
}
?>
<svg class="logomark" viewBox="0 0 64 64" width="<?= (int) $size ?>" height="<?= (int) $size ?>"
  aria-hidden="true"><rect width="64" height="64" rx="19"/><path d="M25 18.5h-6.5v27H25M39 18.5h6.5v27H39"/><circle
  cx="32" cy="32" r="5.5"/></svg>
