<?php

declare(strict_types=1);

/* Every record carries it beside its label: the state dot, the slug and, for a
   work, its translations' dots up to four. In the table each row shows its own
   locale, strong only where it is the install's first, so the other languages read
   as the exception. A row with no word for its standing has no dot, since a dot
   with nothing behind it says nothing. Its title is the whole spine in one line.
   For whoever writes the record it opens the spine dialog. Where the row stands
   for its work, the dots stand beside the chip as links, since a link cannot sit
   in a button: each opens its member, the count the record's Languages card,
   and a missing one that card for whoever may make it.
   Handed `$row`, `$prefix`, `$translations`, null in an install of one language, `$opens` and `$words`;
   `$current`, the install's first locale, may be left out. */

if (!isset($template)) {
  return;
}
$entity = $row->entity;
$public = $row->reach === Profile\Reach::Public;
$word = match (true) {
  $public => $words->get('status.' . $row->state),
  $row->own !== null => $row->own,
  default => '',
};
$title = implode(' · ', array_filter([$word, '/' . $entity->slug->value, $entity->locale->tag,
  'v' . $entity->version, $words->get('spine.changed', ['date' => $row->changed])]));
$dots = $translations ?? [];
$counted = count($dots) > 4;
$shown = $counted ? array_filter($dots,
  fn (Admin\Translation $each): bool => $each->locale === $entity->locale->tag) : $dots;
$strong = $current ?? $entity->locale->tag;
$element = $opens ? 'button' : 'span';
$opening = $element === 'button'
  ? ' type="button" data-spine="' . $template->escape($entity->id->value) . '" aria-haspopup="dialog"' : '';
$dot = $public ? 'state-' . $template->escape($row->state) : 'state-own';
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
  title="<?= $template->escape($title) ?>"><?php if ($public || $row->own !== null): ?><span
  class="state-dot <?= $dot ?>"></span><?php endif ?><span class="visually-hidden"><?= $spoken ?></span><span
  class="spine-address" aria-hidden="true"><?php if ($prefix !== ''): ?><span
  class="spine-prefix"><?= $template->escape($prefix) ?></span><?php endif ?><span
  data-spine-slug><?= $template->escape($entity->slug->value) ?></span></span><?= $linked ? '' : $markers
  ?></<?= $element ?>><?= $linked ? '<span class="locale-links">' . $markers . '</span>' : '' ?>
