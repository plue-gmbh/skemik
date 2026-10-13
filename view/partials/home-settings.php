<?php

declare(strict_types=1);

/* Settings, `manageHouse` only: what the house is set to, then what keeps it
   running. Timezone and admin language, the address a login mail names, which a
   request's `Host` cannot vouch for, with the way it leaves, the relay's sender or
   the host's `mail()`, and the contact `security.txt` names before ours, where
   `config.php` names one, are rows to read and no links: `/install` or `config.php` set them, and a link with no
   screen behind it is a door drawn on a wall. The relay's login is never shown,
   being the machine's credential. Backups and the error log open their screens,
   green while that stands well and red once it failed (`Admin\Health`). Updates
   have no row: the header's version is their door, marked once one is blocked.
   A snapshot nobody has to take is a row and no card; what went wrong with one
   is Needs a look's line. */

if (!isset($template)) {
  return;
}
$reach = $address . ' · ' . $mail;
?>
      <ul class="changes">
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.place')) ?></span></span><span
          class="change-line"><span class="change-what"><?= $template->escape($timezone . ' · ' . $language)
          ?></span></span></div></li>
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.reach')) ?></span></span><span
          class="change-line"><span class="change-what"
          title="<?= $template->escape($reach) ?>"><?= $template->escape($reach) ?></span></span></div></li>
<?php if ($contact !== null): ?>
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.contact')) ?></span></span><span
          class="change-line"><span class="change-what"
          title="<?= $template->escape($contact) ?>"><?= $template->escape($contact) ?></span></span></div></li>
<?php endif ?>
        <li><a class="change" href="/settings/backups"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.backups')) ?></span></span><span
          class="change-line"><span class="change-what <?= $template->escape($backupsHealth?->value) ?>"
          title="<?= $template->escape($backups) ?>"><?= $template->escape($backups) ?></span></span></a></li>
<?php if ($errors !== null): ?>
        <li><a class="change" href="/settings/errors"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('errors.title')) ?></span></span><span
          class="change-line"><span class="change-what <?= $template->escape($errorsHealth?->value) ?>"
          title="<?= $template->escape($errors) ?>"><?= $template->escape($errors) ?></span></span></a></li>
<?php endif ?>
      </ul>
