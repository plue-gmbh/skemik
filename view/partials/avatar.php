<?php

declare(strict_types=1);

/* A person as the header, the Accounts card and an account's card draw them:
   their picture, or the initials of their first two names where they have none
   the reader may see. Decoration beside the name, so hidden from a screen reader. */

if (!isset($template)) {
  return;
}
$initials = implode('', array_map(fn (string $part): string => mb_substr($part, 0, 1),
  array_slice(preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'], 0, 2)));
?>
<?php if ($picture === null): ?>
<span class="avatar" aria-hidden="true"><?= $template->escape(mb_strtoupper($initials)) ?></span>
<?php else: ?>
<img class="avatar" src="<?= $template->escape($picture) ?>" alt="">
<?php endif ?>
