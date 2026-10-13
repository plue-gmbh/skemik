<?php

declare(strict_types=1);

/* The five views, each a link carrying the query so switching keeps it.
   Beside the query bar it is a row of icons; on a
   narrow paper, where five do not fit beside the title, it is a menu of icon and name
   behind one button showing the current view (`$menu`). Both are redrawn with
   the view as the query bar filters (`view/scripts/query-bar.js`), since their links
   carry what was typed. */

use Storage\CollectionView;

if (!isset($template)) {
  return;
}
$class = $menu ? ' class="option"' : '';
?>
<?php if ($menu): ?>
<template id="view-menu" data-view-links><div class="options">
<?php else: ?>
<div class="view-switch" role="group" aria-label="<?= $template->escape($words->get('collection.views')) ?>"
  data-view-links>
<?php endif ?>
<?php foreach (CollectionView::cases() as $each): $name = $words->get('view.'
  . $each->value); $glyph = $template->render('partials/icon', ['name' => $each->value]) ?>
  <a<?= $class ?> href="?<?= $template->escape(http_build_query([...$parameters, 'view' => $each->value,
    'page' => null])) ?>"<?= $each === $view ? ' aria-current="true"' : '' ?>
    title="<?= $template->escape($name) ?>"><?= $glyph ?><span<?= $menu ? ''
    : ' class="visually-hidden"' ?>><?= $template->escape($name) ?></span></a>
<?php endforeach ?>
<?php if ($menu): ?>
</div></template>
<?php else: ?>
</div>
<?php endif ?>
