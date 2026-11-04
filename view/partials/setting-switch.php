<?php

declare(strict_types=1);

/* One choice `config.php` keeps, on or off, switched by posting its opposite, so it
   works without a script and the page it comes back to shows the new state. A
   switch's role, so a screen reader says on or off; disabled where PHP may not
   write the file, described by the note on the page that says so. */

if (!isset($template)) {
  return;
}
?>
    <form method="post" action="<?= $template->escape($action) ?>">
      <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
      <input type="hidden" name="<?= $template->escape($name) ?>" value="<?= $template->escape($value) ?>">
      <button type="submit" class="setting-switch" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>"<?=
        $unwritable === null ? '' : ' disabled aria-describedby="' . $template->escape($unwritable) . '"' ?>><span
        class="switch<?= $on ? ' is-on' : '' ?>" aria-hidden="true"><i></i></span><?= $template->escape($label)
        ?> <span class="settings-store">config.php</span></button>
    </form>
