<?php

declare(strict_types=1);

/* The document around every page: the language the chrome is in, the icons, the
   manifest because Android fills its round home-screen icon only from a maskable
   icon named there and otherwise shrinks the touch icon onto a white disc, the
   one stylesheet with its first font asked for before the stylesheet names it, the
   shared behaviour, and Vue only where a page mounts an island.
   The referrer policy is said here a second time, as `Http\SecurityHeaders`
   says it, because a host's front can replace the header with a looser one and
   a browser reads the document's own last. */

if (!isset($template)) {
  return;
}
?>
<!doctype html>
<html lang="<?= $template->escape($words->language) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="same-origin">
<title><?= $template->escape($title === '' ? 'Skemik' : $title . ' · Skemik') ?></title>
<link rel="icon" href="<?= $template->asset('icons/favicon.ico') ?>" sizes="32x32">
<link rel="apple-touch-icon" href="<?= $template->asset('icons/apple-touch-icon.png') ?>">
<link rel="manifest" href="<?= $template->asset('manifest.webmanifest') ?>">
<link rel="preload" href="/view/vendor/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $template->asset('style.css') ?>">
<?php if ($islands): ?>
<script src="<?= $template->asset('vendor/vue.global.prod.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/pickers.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/edge-picker.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/cell-editor.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/spine-dialog.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/ticks.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/sheet-sending.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/sheet.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/library.js') ?>" defer></script>
<?php endif ?>
<script src="<?= $template->asset('scripts/query-bar.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/app.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/trash.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/forms.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/grants.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/accounts.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/fields.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/sidebar.js') ?>" defer></script>
<script src="<?= $template->asset('scripts/feed-panel.js') ?>" defer></script>
</head>
<?= $body ?>
</html>
