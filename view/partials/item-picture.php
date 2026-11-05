<?php

declare(strict_types=1);

/* The picture a list row, a card or a grid tile leads with, decorative beside the label
   that names it: an asset's own preview, else its Poster's, since GD makes none of a video,
   else the first image the record points at (`Admin\Glance::image`), else a glyph — the
   file's kind for an asset, the type's elsewhere — and the one load is the preview
   (`Admin\Rows::preview`). A Poster the reader may not see is a gone chip, which has none.
   A video's Poster carries a play mark, else it reads as a picture. */

if (!isset($template)) {
  return;
}
$entity = $row?->entity;
$mime = (string) $entity?->props->string('encoding_format');
$image = $glance->image();
$preview = match (true) {
  $entity === null => null,
  $glance->media() => Admin\Rows::preview($entity) ?? ($row->chips['thumbnail'][0] ?? null)?->preview,
  default => $image === null ? null : ($row->chips[$image->field->key][0] ?? null)?->preview,
};
$glyph = $glance->media() ? match (strtok($mime, '/')) {
  'image', 'video', 'audio' => strtok($mime, '/'),
  default => 'file',
} : Admin\SidebarEntry::glyphOf($collection->type);
?>
<span class="<?= $template->escape($class) ?>" aria-hidden="true"><?php if ($preview === null): ?><span
  class="item-glyph"><?= $template->render('partials/glyph', ['name' => $glyph]) ?></span><?php
  else: ?><img src="<?= $template->escape($preview) ?>" alt="" loading="lazy"><?php if (str_starts_with($mime,
  'video/')): ?><span class="play-mark"><?= $template->render('partials/icon', ['name' => 'play'])
  ?></span><?php endif ?><?php endif ?></span>
