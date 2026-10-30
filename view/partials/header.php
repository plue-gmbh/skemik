<?php

declare(strict_types=1);

/* On every admin page, one row: the menu button on a narrow screen, the mark as
   the link home; then the house's own sites (`Storage\Sites`), where the row has
   room for them, the search, which opens into its field and answers on `/find`,
   the Feed button and the account. Settings, updates and help are no header
   items, because each belongs to a screen: the version and the door to the
   update screen are the Settings card's files row. The admin has no light/dark
   switch but three materials, chrome, paper and terminal, each a scope redefining
   the same tokens in `view/style.css`, so a region says its role by its material
   and every component sits right on any ground; the scope sets `color` too, or
   inherited text keeps the paper's ink. */

if (!isset($template)) {
  return;
}
$avatar = trim($template->render('partials/avatar', ['name' => $menu->name, 'picture' => $menu->picture]));
$who = $own === null ? 'p class="account-who"' : 'a class="account-who" href="' . $template->escape($own) . '"';
?>
<header class="header" data-material="chrome-deep">
  <button type="button" class="header-menu" aria-controls="sidebar" aria-expanded="false"
    aria-label="<?= $template->escape($words->get('header.menu')) ?>"><?= $template->render('partials/icon',
      ['name' => 'menu']) ?></button>
  <span class="header-brand">
    <a class="header-home" href="/"><?= $template->render('partials/mark', ['size' => 26]) ?><b>Skemik<span
      class="trademark">™</span></b></a>
  </span>
  <span class="header-space"></span>
<?php if ($sites !== []): ?>
  <nav class="header-sites" aria-label="<?= $template->escape($words->get('header.sites')) ?>">
<?= $template->render('partials/site-links', ['sites' => $sites]) ?>
  </nav>
<?php endif ?>
  <form class="header-search" action="/find" method="get" role="search" data-header-search>
    <button type="button" aria-expanded="false" aria-controls="header-search-field"
      aria-label="<?= $template->escape($words->get('header.search')) ?>"
      title="<?= $template->escape($words->get('header.search')) ?>"><?= $template->render('partials/icon',
        ['name' => 'search']) ?></button>
    <input type="search" name="q" id="header-search-field" autocomplete="off"
      placeholder="<?= $template->escape($words->get('header.search')) ?>"
      aria-label="<?= $template->escape($words->get('header.search')) ?>">
    <button type="button" class="header-search-close" aria-label="<?= $template->escape(
      $words->get('header.search_close')) ?>" data-header-search-close><?= $template->render('partials/icon',
        ['name' => 'close']) ?></button>
  </form>
  <button type="button" class="header-feed" aria-controls="feed-panel"
    aria-expanded="<?= $feed->open ? 'true' : 'false' ?>"
    data-feed-button><?= $template->escape($words->get('header.feed')) ?></button>
  <details class="account">
    <summary><?= $avatar ?><span class="account-name"><?= $template->escape($menu->name) ?></span></summary>
    <div class="account-menu">
      <p class="account-label"><?= $template->escape($words->get('header.logged_in')) ?></p>
      <<?= $who ?>><?= $avatar ?><span class="account-lines"><b><?= $template->escape($menu->name) ?></b><span
        class="account-email"><?= $template->escape($menu->email) ?></span></span></<?= strtok($who, ' ') ?>>
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
