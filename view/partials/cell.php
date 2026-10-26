<?php

declare(strict_types=1);

/* A value displayed, by kind (`Admin\Cells`) — the renderer every view reuses.
   A select is a pill with its word, titled with the term where the two differ,
   because the term is what the feed ships. A reference leads to its record, as
   does a flattened key that refers to one rather than holding a word, except
   where the reader edits the field: there the whole cell opens its picker,
   which links each target. A list of them shows and numbers every one, since a
   member behind a count is one nobody checks; one that is gone and one the
   reader may not see are both ⇢ missing; an image's chip is its preview and its
   file name, and what an edge says of its target stands beside it
   (`Admin\Cells`). Empty is — for every kind. A Markdown field's links to
   records are counted here and drawn as chips on the record screen (`Admin\Prose`). */

use Profile\FieldKind;

if (!isset($template)) {
  return;
}
$linked ??= true;
$chip = function (Admin\Chip $chip, ?int $number, string $said) use ($template, $words, $linked): string {
  if ($chip->gone) {
    return '<span class="chip is-gone">' . $template->escape($words->get('cell.gone')) . '</span>';
  }
  $mark = $number === null
    ? '<span class="chip-mark">' . $template->render('partials/glyph', ['name' => $chip->glyph]) . '</span>'
    : '<span class="chip-mark is-count" aria-hidden="true">' . $number . '</span>';
  $picture = $chip->preview === null ? ''
    : '<img class="chip-preview" src="' . $template->escape($chip->preview) . '" alt="" loading="lazy">';
  $noAlt = $chip->noAlt ? '<span class="no-alt">' . $template->escape($words->get('cell.no_alt')) . '</span>' : '';

  $link = $linked && $chip->href !== null;
  $tag = $link ? 'a href="' . $template->escape((string) $chip->href) . '"' : 'span';

  $said = $said === '' ? '' : '<span class="chip-says">' . $template->escape($said) . '</span>';

  return sprintf('<%s class="chip" title="%s">%s%s%s</%s>%s%s',
    $tag, $template->escape($chip->title), $mark, $picture,
    $template->escape($chip->label), $link ? 'a' : 'span', $noAlt, $said);
};
?>
<?php if ($empty): ?>
<span class="cell-empty" aria-hidden="true">—</span><span
  class="visually-hidden"><?= $template->escape($words->get('cell.empty')) ?></span>
<?php elseif ($kind === FieldKind::Boolean): ?>
<span class="switch<?= $value === true ? ' is-on' : '' ?>" aria-hidden="true"><i></i></span><span
  class="visually-hidden"><?= $template->escape($words->get($value === true ? 'cell.yes' : 'cell.no')) ?></span>
<?php elseif ($kind === FieldKind::Select): ?>
<span class="pill" lang="<?= $template->escape($locale) ?>"<?= $shown !== $value ? ' title="'
  . $template->escape($words->get('cell.term', ['term' => (string) $value]))
  . '"' : '' ?>><?= $template->escape($shown) ?></span>
<?php elseif (($kind->isReference() || $chips !== []) && !($many ?? false)): ?>
<?= $chip($chips[0], null, $says[0] ?? '') ?>
<?php elseif ($kind->isReference()): ?>
<span class="chips"><?php foreach ($chips as $index => $each): ?><?= $chip($each, $index + 1,
  $says[$index] ?? '') ?><?php endforeach ?></span>
<?php elseif ($kind === FieldKind::Markdown):
  $plain = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[#*_>`]/u', '', $shown)));
  $links = preg_match_all('/\]\(skemik:[^)]+\)/u', $shown);
?>
<span class="cell-markdown"><span lang="<?= $template->escape($locale) ?>"><?= $template->escape(mb_strimwidth($plain,
  0, 60, '…')) ?></span><?php if ($links > 0): ?> <span
  class="cell-note">⇢ <?= $links ?></span><?php endif ?></span>
<?php elseif (in_array($kind, [FieldKind::Date, FieldKind::DateTime, FieldKind::Duration], true)): ?>
<span title="<?= $template->escape((string) $value) ?>"><?= $template->escape($shown) ?></span>
<?php elseif (in_array($kind, [FieldKind::Text, FieldKind::LongText], true)): ?>
<span lang="<?= $template->escape($locale) ?>"><?= $template->escape($shown) ?></span>
<?php else: ?>
<?= $template->escape($shown) ?>
<?php endif ?>
