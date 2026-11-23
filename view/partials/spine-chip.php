<?php

declare(strict_types=1);

/* Every record carries it beside its label: the state dot, the slug and, for a
   work, its translations' dots up to four. In the table each row shows its own
   locale, strong only where it is the install's first, so the other languages read
   as the exception. A type that never publishes shows its state too, draft, in
   review or archived, since an organisation keeps them among itself, and a message
   nobody has read yet its slug in the accent colour and strong, since a colour
   alone marks nothing for whoever cannot tell it apart. Its title is the whole spine in one line.
   For whoever writes the record it opens the spine dialog. Where the row stands
   for its work, the dots stand beside the chip as links, since a link cannot sit
   in a button: each opens its member, the count the record's Languages card,
   and a missing one that card for whoever may make it. The record screen hands
   its own language alone, since its Languages card lists the others, and `$locked` where its type never
   publishes, a lock beside the chip. Handed `$row`, `$prefix`, `$translations`, null in an install of one
   language, `$opens` and `$words`; `$current`, the install's first locale, and `$locked` may be left out. */

if (!isset($template)) {
  return;
}
$entity = $row->entity;
$unread = $row->unread ? $words->get('status.unread') : '';
$title = implode(' · ', array_filter([$words->get('status.' . $row->state), $unread, '/' . $entity->slug->value,
  $entity->locale->tag, 'v' . $entity->version, $words->get('spine.changed', ['date' => $row->changed])]));
$dots = $translations ?? [];
$counted = count($dots) > 4;
$shown = $counted ? array_filter($dots,
  fn (Admin\Translation $each): bool => $each->locale === $entity->locale->tag) : $dots;
$strong = $current ?? $entity->locale->tag;
$locked ??= false;
$element = $opens ? 'button' : 'span';
$opening = $element === 'button'
  ? ' type="button" data-spine="' . $template->escape($entity->id->value) . '" aria-haspopup="dialog"' : '';
$spoken = ($element === 'button' ? $template->escape($words->get('spine.open')) . ' – ' : '')
  . $template->escape($title);
$present = count(array_filter($dots, fn (Admin\Translation $each): bool => $each->record !== null));
$languages = array_find($dots, fn (Admin\Translation $each): bool => $each->locale === $entity->locale->tag)?->href;
$marker = fn (string $class, string $text, ?string $href, string $label, string $own = ''): string => $href === null
  ? sprintf('<span class="locale-dot%s"%s aria-hidden="true">%s</span>', $class, $own, $text)
  : sprintf('<a class="locale-dot%s"%s href="%s" aria-label="%s">%s</a>', $class, $own, $template->escape($href),
    $template->escape($label), $text);
$markers = '';
foreach ($shown as $each) {
  $missing = $each->record === null;
  $markers .= $marker(($missing ? ' is-missing' : '') . ($each->locale === $strong ? ' is-current' : ''),
    $template->escape($each->marker), $each->href,
    $words->get($missing ? 'record.locale_missing' : 'record.locale_open', ['locale' => $each->locale]),
    $each->locale === $entity->locale->tag ? ' data-spine-locale' : '');
}
$linked = array_any($dots, fn (Admin\Translation $each): bool => $each->href !== null);
$markers .= $counted ? $marker(' is-count', $present . '/' . count($dots),
  $linked && $languages !== null ? $languages . Admin\Items::LANGUAGES : null,
  $words->get('record.languages') . ' ' . $present . '/' . count($dots)) : '';
?>
<<?= $element ?> class="spine-chip<?= $prefix === '' ? '' : ' is-full' ?>"<?= $opening ?>
  title="<?= $template->escape($title) ?>"><span
  class="state-dot state-<?= $template->escape($row->state) ?>"></span><span
  class="visually-hidden"><?= $spoken ?></span><span
  class="spine-address" aria-hidden="true"><?php if ($prefix !== ''): ?><span
  class="spine-prefix"><?= $template->escape($prefix) ?></span><?php endif ?><span
  data-spine-slug<?= $row->unread ? ' class="spine-unread"' : '' ?>><?= $template->escape($entity->slug->value)
  ?></span></span><?= $linked ? '' : $markers
  ?></<?= $element ?>><?= $linked ? '<span class="locale-links">' . $markers . '</span>' : '' ?><?php if ($locked):
  ?> <span class="tag-never" title="<?= $template->escape($words->get('record.private')) ?>"><?= $template->render(
  'partials/icon', ['name' => 'locked']) ?><span class="visually-hidden"><?= $template->escape(
  $words->get('record.private')) ?></span></span><?php endif ?>
