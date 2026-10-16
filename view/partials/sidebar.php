<?php

declare(strict_types=1);

/* The collection list `Admin\Sidebar` computed, drawn on every admin page;
   only the list scrolls, positioned so the marks' hidden words scroll inside it
   rather than lengthen the page, and Export stays at its foot for every role,
   under the collections it reads (`Admin\ExportController`), and alone: a
   snapshot nobody has to take is no button for every page, so Backup is a row
   of the Settings card (`view/partials/home-settings.php`). On narrow screens
   it is a drawer behind the header's menu button (`view/scripts/sidebar.js`),
   and the house's own sites, which the header then has no room for, head it. */

if (!isset($template)) {
  return;
}
?>
<nav id="sidebar" class="sidebar" data-material="chrome-deep"
  aria-label="<?= $template->escape($words->get('sidebar.label')) ?>">
<?php if ($sites !== []): ?>
  <div class="sidebar-sites">
<?= $template->render('partials/site-links', ['sites' => $sites]) ?>
  </div>
<?php endif ?>
  <ul class="sidebar-list">
<?php foreach ($entries as $entry): ?>
    <li><a class="sidebar-entry<?= $entry->count === 0 ? ' is-empty' : '' ?>"
      href="/c/<?= $template->escape($entry->slug) ?>"<?= $entry->slug === $open ? ' aria-current="page"' : '' ?>>
      <span class="sidebar-glyph"><?= $template->render('partials/glyph', ['name' => $entry->glyph]) ?></span>
      <span class="sidebar-name"><?= $template->escape($entry->name) ?></span>
<?php if ($entry->writers !== []): $writes = $words->get('sidebar.writes', ['names' => implode(', ',
  $entry->writers)]) ?>
      <span class="sidebar-grant" title="<?= $template->escape($writes) ?>"><?= $template->render('partials/glyph',
        ['name' => 'key']) ?><span class="visually-hidden"><?= $template->escape($writes) ?></span></span>
<?php endif ?>
<?php if ($entry->served): ?>
      <span class="sidebar-served" title="<?= $template->escape($words->get('sidebar.served',
        ['address' => '/feed/' . $locale . '/' . $entry->slug])) ?>"><span
        class="visually-hidden"><?= $template->escape($words->get('sidebar.served',
        ['address' => '/feed/' . $locale . '/' . $entry->slug])) ?></span></span>
<?php endif ?>
      <span class="sidebar-count"><?= $entry->count ?></span>
    </a></li>
<?php endforeach ?>
  </ul>
<?php if ($exports): ?>
  <div class="sidebar-tools">
    <a class="sidebar-export" href="/export"<?= $open === 'export' ? ' aria-current="page"'
      : '' ?>><?= $template->render('partials/icon',
      ['name' => 'export']) ?><?= $template->escape($words->get('sidebar.export')) ?></a>
  </div>
<?php endif ?>
</nav>
