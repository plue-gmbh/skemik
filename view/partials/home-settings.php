<?php

declare(strict_types=1);

/* Settings, `manageHouse` only: what the house is set to, then what keeps it
   running. Timezone and admin language, the address a login mail names, which a
   request's `Host` cannot vouch for, with the way it leaves, the relay's sender or
   the host's `mail()`, and where `config.php` names them the contact
   `security.txt` names before ours and the line under the login form, are rows
   to read and no links: `/install` or `config.php` set them, and a link with no
   screen behind it is a door drawn on a wall. The one exception is the line's
   own address where `config.php` names one, which the row opens in a new tab,
   as the header's site links do, since it leaves the admin. The
   relay's login is never shown, being the machine's credential. Backups and the
   error log open their screens, an arrow telling them from the rows, and the
   release's files have none, each green while it stands well and red once it
   failed (`Admin\Health`). A row's text is its tooltip only once it is long
   enough to be cut, since the card is narrow and a short one repeated says
   nothing. Updates
   have no row: the header's version is their door, marked once one is blocked.
   A snapshot nobody has to take is a row and no card; what went wrong with one,
   or with a file, is Needs a look's line. */

if (!isset($template)) {
  return;
}
$reach = $address . ' · ' . $mail;
$go = '<span class="change-go">' . $template->render('partials/icon', ['name' => 'right']) . '</span>';
$what = fn (string $text, ?Admin\Health $health = null): string => sprintf('<span class="change-what%s"%s>%s</span>',
  $health === null ? '' : ' ' . $health->value,
  mb_strlen($text) > 50 ? ' title="' . $template->escape($text) . '"' : '', $template->escape($text));
?>
      <ul class="changes">
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.place')) ?></span></span><span
          class="change-line"><?= $what($timezone . ' · ' . $language) ?></span></div></li>
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.reach')) ?></span></span><span
          class="change-line"><?= $what($reach) ?></span></div></li>
<?php if ($contact !== null): ?>
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.contact')) ?></span></span><span
          class="change-line"><?= $what($contact) ?></span></div></li>
<?php endif ?>
<?php if ($note !== null): $open = $link === null ? '<div class="change">'
  : '<a class="change" href="' . $template->escape($link) . '" target="_blank" rel="noreferrer">' ?>
        <li><?= $open ?><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.login_note')) ?></span><?= $link === null ? ''
          : $go ?></span><span class="change-line"><?= $what($note) ?></span><?= $link === null ? '</div>'
          : '</a>' ?></li>
<?php endif ?>
        <li><a class="change" href="/settings/backups"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.backups')) ?></span><?= $go ?></span><span
          class="change-line"><?= $what($backups, $backupsHealth) ?></span></a></li>
<?php if ($errors !== null): ?>
        <li><a class="change" href="/settings/errors"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('errors.title')) ?></span><?= $go ?></span><span
          class="change-line"><?= $what($errors, $errorsHealth) ?></span></a></li>
<?php endif ?>
<?php if ($files !== null): ?>
        <li><div class="change"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.files')) ?></span></span><span
          class="change-line"><?= $what($files, $filesHealth) ?></span></div></li>
<?php endif ?>
      </ul>
