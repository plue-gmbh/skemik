<?php

declare(strict_types=1);

/* The house's own sites (`Admin\Sidebar::sites`), in the header where it has room
   and heading the drawer where it has not. A host is cut to 40 characters, and to
   20 once four stand, so the row keeps its Feed button and its account; a cut one
   is named whole on hover and to a screen reader. */

if (!isset($template)) {
  return;
}
$cut = count($sites) >= 4 ? 20 : 40;
?>
<?php foreach ($sites as $url => $host): $shown = mb_strimwidth($host, 0, $cut, '…');
  $whole = $shown === $host ? '' : $template->escape($host) ?>
    <a class="header-site" href="<?= $template->escape($url) ?>" target="_blank" rel="noreferrer"<?= $whole === ''
      ? '' : ' title="' . $whole . '" aria-label="' . $whole . '"' ?>><?= $template->escape($shown)
      ?><?= $template->render('partials/icon', ['name' => 'open']) ?></a>
<?php endforeach ?>
