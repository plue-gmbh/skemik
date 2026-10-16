<?php

declare(strict_types=1);

/* Every admin screen: first, shown only to a keyboard, the way past the thirty
   collections to the paper; the chrome's L of header and sidebar, the paper
   beside it and the feed panel to its right (`partials/feed-panel`), above the
   paper's content the sentence a screen may open with and the warning that the
   data folder runs short, the header's version marked while a release cannot be
   installed automatically (`Admin\Probes`), and at its foot the copyright line,
   pushed to the bottom where a screen is short. */

if (!isset($template)) {
  return;
}
ob_start();
?>
<body class="admin">
<a class="skip-link" href="#main"><?= $template->escape($words->get('frame.skip')) ?></a>
<?= $template->render('partials/header', ['words' => $words, 'menu' => $menu, 'csrf' => $csrf,
  'updates' => $account->can(Auth\Capability::RunUpdate), 'blocked' => $update, 'feed' => $feed,
  'open' => $open, 'sites' => $sites]) ?>
<div class="admin-body">
<?= $template->render('partials/sidebar', ['words' => $words, 'entries' => $entries, 'open' => $open,
  'locale' => $locale, 'exports' => $account->can(Auth\Capability::ExportHoldings), 'sites' => $sites]) ?>
<main class="paper" id="main"><?php if (($notice ?? '') !== ''): ?><p class="notice"
  role="status"><?= $template->escape($notice) ?></p><?php endif ?><?php if ($space !== ''): ?><p class="notice"
  role="status"><?= $template->escape($space) ?></p><?php endif ?><?= $main ?><?= $template->render('partials/footer',
  ['words' => $words, 'ground' => false]) ?></main>
<?= $template->render('partials/feed-panel', ['words' => $words, 'feed' => $feed]) ?>
</div>
<div class="live-region visually-hidden" role="status" aria-live="polite" data-live-region></div>
<?= $template->render('partials/glyphs') ?>
</body>
<?php
echo $template->render('partials/document', ['words' => $words, 'title' => $title, 'islands' => $islands,
  'body' => (string) ob_get_clean()]);
