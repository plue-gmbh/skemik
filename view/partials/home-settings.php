<?php

declare(strict_types=1);

/* Settings, `manageHouse` only: what the house is set to, then what keeps it
   running, each row the door to its own screen, so no arrow marks one. The
   first opens the general screen and says the timezone, the admin language and
   the way mail leaves, the relay's sender or the host's `mail()`, never the
   relay's login, which is the machine's credential, and is marked while the
   host slows every answer. Backups, the error log and
   the files are green while they stand well and red once they failed
   (`Admin\Health`). The updates row names the release that runs and is the door to
   the update screen for whoever may run one, marked once a release is blocked,
   the reason being Needs a look's. A row's text is its tooltip only once it is
   long enough to be cut, since the card is narrow and a short one repeated says
   nothing. What went wrong with a snapshot, or with a file, is Needs a look's line. */

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
        <li><a class="change" href="/settings/backups"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('home.backups')) ?></span></span><span
          class="change-line"><?= $what($backups, $backupsHealth) ?></span></a></li>
<?php if ($errors !== null): ?>
        <li><a class="change" href="/settings/errors"><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('errors.title')) ?></span></span><span
          class="change-line"><?= $what($errors, $errorsHealth) ?></span></a></li>
<?php endif ?>
<?php $door = $updates ? '<a class="change" href="/settings/update">' : '<div class="change">' ?>
        <li><?= $door ?><span class="change-head"><span
          class="change-name"><?= $template->escape($words->get('update.title')) ?></span></span><span
          class="change-line"><?= $what($files, $filesHealth) ?></span><?= $updates ? '</a>' : '</div>' ?></li>
      </ul>
