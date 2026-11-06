<?php

declare(strict_types=1);

/* Settings, `manageInstall` only: what the install is set to, the release it runs and
   what keeps it running, each row the door to its own screen, so no arrow marks
   one. General says how mail leaves but never the relay's login, the machine's
   credential, and is marked while the host slows every answer. Updates is a door
   only for whoever may run one, and is marked once a release is blocked. Files,
   backups and the error log are green or red (`Admin\Health`); why is Needs a
   look's line. A row's text is its tooltip only once it is cut, since a short
   one repeated says nothing. Handed `$general`, `$files`, `$backups` and
   `$errors`, null for none, each with its health, `$updates` and `$words`. */

if (!isset($template)) {
  return;
}
$what = fn (string $text, ?Admin\Health $health = null): string => sprintf('<span class="change-what%s"%s>%s</span>',
  $health === null ? '' : ' ' . $health->value,
  mb_strlen($text) > 50 ? ' title="' . $template->escape($text) . '"' : '', $template->escape($text));
?>
      <ul class="changes">
        <li><a class="change" href="/settings/general"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.general')) ?></span></span><span
          class="change-line"><?= $what($general, $generalHealth) ?></span></a></li>
<?php $door = $updates ? '<a class="change" href="/settings/update">' : '<div class="change">' ?>
        <li><?= $door ?><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('update.title')) ?></span></span><span
          class="change-line"><?= $what($files, $filesHealth) ?></span><?= $updates ? '</a>' : '</div>' ?></li>
        <li><a class="change" href="/settings/backups"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.backups')) ?></span></span><span
          class="change-line"><?= $what($backups, $backupsHealth) ?></span></a></li>
<?php if ($errors !== null): ?>
        <li><a class="change" href="/settings/errors"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('errors.title')) ?></span></span><span
          class="change-line"><?= $what($errors, $errorsHealth) ?></span></a></li>
<?php endif ?>
      </ul>
