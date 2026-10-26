<?php

declare(strict_types=1);

/* On every admin page, one row: the menu button on a narrow screen, the mark as
   the link home and beside it the version, the owner's door to the update screen,
   marked while a release cannot be installed automatically, the reason being Needs a
   look's; then the house's own sites (`Storage\Sites`), where the row has room
   for them, the Feed button and the account. Search, settings and help are no
   header items, because each belongs to a screen. The admin has no light/dark
   switch but three materials, chrome, paper and terminal, each a scope redefining
   the same tokens in `view/style.css`, so a region says its role by its material
   and every component sits right on any ground; the scope sets `color` too, or
   inherited text keeps the paper's ink. */

if (!isset($template)) {
  return;
}
$initials = implode('', array_map(fn (string $part): string => mb_substr($part, 0, 1),
  array_slice(preg_split('/\s+/u', trim($menu->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'], 0, 2)));
$avatar = $menu->picture === null
  ? '<span class="avatar" aria-hidden="true">' . $template->escape(mb_strtoupper($initials)) . '</span>'
  : '<img class="avatar" src="' . $template->escape($menu->picture) . '" alt="">';
?>
<header class="header" data-material="chrome-deep">
  <button type="button" class="header-menu" aria-controls="sidebar" aria-expanded="false"
    aria-label="<?= $template->escape($words->get('header.menu')) ?>"><?= $template->render('partials/icon',
      ['name' => 'menu']) ?></button>
  <span class="header-brand">
    <a class="header-home" href="/"><?= $template->render('partials/mark', ['size' => 26]) ?><b>Skemik<span
      class="trademark">™</span></b></a>
<?php if ($updates): $door = $blocked ?? $words->get('header.update') ?>
    <a class="header-version<?= $blocked === null ? '' : ' is-marked' ?>" href="/settings/update"
      <?= $open === 'update' ? 'aria-current="page" ' : '' ?>title="<?= $template->escape($door) ?>"><?=
      $template->escape(Update\Version::APP) ?><span
      class="visually-hidden"> – <?= $template->escape($door) ?></span></a>
<?php else: ?>
    <span class="header-version"><?= $template->escape(Update\Version::APP) ?></span>
<?php endif ?>
  </span>
  <span class="header-space"></span>
<?php if ($sites !== []): ?>
  <nav class="header-sites" aria-label="<?= $template->escape($words->get('header.sites')) ?>">
<?= $template->render('partials/site-links', ['sites' => $sites]) ?>
  </nav>
<?php endif ?>
  <button type="button" class="header-feed" aria-controls="feed-panel"
    aria-expanded="<?= $feed->open ? 'true' : 'false' ?>"
    data-feed-button><?= $template->escape($words->get('header.feed')) ?></button>
  <details class="account">
    <summary><?= $avatar ?><span class="account-name"><?= $template->escape($menu->name) ?></span></summary>
    <div class="account-menu">
      <p class="account-label"><?= $template->escape($words->get('header.logged_in')) ?></p>
      <p class="account-who"><?= $avatar ?><span class="account-lines"><b><?= $template->escape($menu->name) ?></b><span
        class="account-email"><?= $template->escape($menu->email) ?></span></span></p>
      <form method="post" action="/logout">
        <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
        <button type="submit"><?= $template->escape($words->get('header.logout')) ?></button>
        <button type="submit" name="everywhere" value="1" aria-describedby="account-others"<?= $menu->others === 0
          ? ' disabled' : '' ?>><?= $template->escape($words->get('header.logout_everywhere')) ?></button>
        <p class="account-others"
          id="account-others"><?= $template->escape($words->get(match ($menu->others) { 0 => 'header.others_none',
          1 => 'header.other', default => 'header.others' }, ['n' => (string) $menu->others])) ?></p>
      </form>
    </div>
  </details>
</header>
