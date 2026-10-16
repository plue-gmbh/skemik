<?php

declare(strict_types=1);

/* Every record carries it beside its label: the state dot, the slug and, for a
   work, its translations' dots up to three. In the table each row shows its own
   locale, strong only where it is the house's first, so the other languages read
   as the exception. A row with no word for its standing has no dot, since a dot
   with nothing behind it says nothing. Its title is the whole spine in one line.
   For whoever writes the record it opens the spine dialog. */

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
$changed = (new IntlDateFormatter($words->language, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, 'UTC'))
  ->format(new DateTimeImmutable($row->changed === '' ? $entity->updatedAt : $row->changed,
    new DateTimeZone('UTC')));
$title = implode(' · ', array_filter([$word, '/' . $entity->slug->value, $entity->locale->tag,
  'v' . $entity->version, $words->get('spine.changed', ['date' => (string) $changed])]));
$dots = $translations ?? [];
$shown = count($dots) > 3 ? array_filter($dots,
  fn (Admin\Translation $each): bool => $each->locale === $entity->locale->tag) : $dots;
$short = fn (string $tag): string => explode('-', $tag)[0];
$strong = $current ?? $entity->locale->tag;
$element = ($opens ?? false) ? 'button' : 'span';
$opening = $element === 'button'
  ? ' type="button" data-spine="' . $template->escape($entity->id->value) . '" aria-haspopup="dialog"' : '';
$dot = $public ? 'state-' . $template->escape($row->state) : 'state-own';
$spoken = ($element === 'button' ? $template->escape($words->get('spine.open')) . ' – ' : '')
  . $template->escape($title);
$present = count(array_filter($dots, fn (Admin\Translation $each): bool => $each->record !== null));
?>
<<?= $element ?> class="spine-chip<?= ($prefix ?? '') === '' ? '' : ' is-full' ?>"<?= $opening ?>
  title="<?= $template->escape($title) ?>"><?php if ($public || $row->own !== null): ?><span
  class="state-dot <?= $dot ?>"></span><?php endif ?><span class="visually-hidden"><?= $spoken ?></span><span
  class="spine-address" aria-hidden="true"><?php if (($prefix ?? '') !== ''): ?><span
  class="spine-prefix"><?= $template->escape($prefix) ?></span><?php endif ?><span
  data-spine-slug><?= $template->escape($entity->slug->value) ?></span></span><?php foreach ($shown as $each): ?><span
  class="locale-dot<?= $each->record === null ? ' is-missing' : '' ?><?= $each->locale === $strong
    ? ' is-current' : '' ?>"<?= $each->locale === $entity->locale->tag ? ' data-spine-locale' : '' ?>
  aria-hidden="true"><?= $template->escape($short($each->locale)) ?></span><?php endforeach ?><?php
  if (count($dots) > 3): ?><span class="locale-dot is-count" aria-hidden="true"><?= $present ?>/<?= count($dots)
  ?></span><?php endif ?></<?= $element ?>>
