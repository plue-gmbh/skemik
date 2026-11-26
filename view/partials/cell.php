<?php

declare(strict_types=1);

/* A value displayed, by kind (`Admin\Cells`), the renderer every view reuses. A select's pill is titled with
   its term where the word differs, the term being what the feed ships. A reference, or a key that refers, links
   to its record except where the reader edits the field, whose picker links each target. A list shows every
   member, since one behind a count is one nobody checks, each with its type's glyph, since a field may take
   more than one type; a gone one and one the reader may not see are both ⇢ missing. What an edge says of its
   target stands inside its chip, so in a list it reads as that member's, and the chip's title holds a long name
   whole. A Markdown field's links to records are counted here and drawn on the record screen (`Admin\Prose`).
   A row's value beside its name is framed lighter than a chip, since a row is no record. Handed `$kind`,
   `$value`, `$shown`, `$chips`, `$empty` and `$words`, and `$locale`, `$many`, `$linked`, `$links`, `$pairs`
   and `$says`, which an empty cell leaves out, and `$named`, which a blank row's empty cell shows where no
   heading names it. */

use Profile\FieldKind;

if (!isset($template)) {
  return;
}
$linked ??= true;
$links ??= [];
$pairs ??= [];
$many ??= false;
$says ??= [];
/* A name in the dash's place is text to read, so `is-named` draws it at 4.5:1 on a required cell's tint too. */
$named ??= null;
$chip = function (Admin\Chip $chip, string $said) use ($template, $words, $linked): string {
  if ($chip->gone) {
    return '<span class="chip is-gone">' . $template->escape($words->get('cell.gone')) . '</span>';
  }
  $mark = '<span class="chip-mark">' . $template->render('partials/glyph', ['name' => $chip->glyph]) . '</span>';
  $picture = $chip->preview === null ? ''
    : '<img class="chip-preview" src="' . $template->escape($chip->preview) . '" alt="" loading="lazy">';
  $noAlt = $chip->noAlt ? '<span class="no-alt">' . $template->escape($words->get('cell.no_alt')) . '</span>' : '';

  $link = $linked && $chip->href !== null;
  $tag = $link ? 'a href="' . $template->escape((string) $chip->href) . '"' : 'span';

  $said = $said === '' ? '' : '<span class="chip-says">' . $template->escape($said) . '</span>';

  return sprintf('<%s class="chip" title="%s">%s%s<span class="chip-label">%s</span>%s</%s>%s',
    $tag, $template->escape($chip->label . ' · ' . $chip->title), $mark, $picture,
    $template->escape($chip->label), $said, $link ? 'a' : 'span', $noAlt);
};
?>
<?php if ($empty): ?>
<span class="cell-empty<?= $named === null ? '' : ' is-named' ?>"
  aria-hidden="true"><?= $named === null ? '—' : $template->escape($named) ?></span><span
  class="visually-hidden"><?= $template->escape($words->get('cell.empty')) ?></span>
<?php elseif ($pairs !== []): ?>
<span class="pairs"><?php foreach ($pairs as $index => $each):
  $leads = $linked && $each->href !== null;
  $tag = $leads ? 'a href="' . $template->escape((string) $each->href) . '"'
    . ($each->opensBeside() ? ' target="_blank" rel="noreferrer"' : '') : 'span';
  ?><?= $index === 0 ? '' : '<span class="visually-hidden">, </span>' ?><<?= $tag ?> class="pair"><span
  class="pair-name"<?= $each->lang === null ? '' : ' lang="' . $template->escape($each->lang) . '"'
  ?>><?= $template->escape($each->word) ?></span><?php if ($each->value !== null): ?><span
  class="visually-hidden">: </span><span class="pair-value"><?= $template->escape($each->value) ?></span><?php
  endif ?></<?= $leads ? 'a' : 'span' ?>><?php endforeach ?></span>
<?php elseif ($links !== []): ?>
<span><?php foreach ($links as $index => $each): ?><?= $index === 0 ? ''
  : ', ' ?><?php if ($each->href === null): ?><?= $template->escape($each->word) ?><?php else: ?><a
  href="<?= $template->escape($each->href) ?>"<?= $each->opensBeside() ? ' target="_blank" rel="noreferrer"' : ''
  ?>><?= $template->escape($each->word) ?></a><?php endif ?><?php endforeach ?></span>
<?php elseif ($kind === FieldKind::Boolean): ?>
<span class="switch<?= $value === true ? ' is-on' : '' ?>" aria-hidden="true"><i></i></span><span
  class="visually-hidden"><?= $template->escape($words->get($value === true ? 'cell.yes' : 'cell.no')) ?></span>
<?php elseif ($kind === FieldKind::Select): ?>
<span class="pill"<?= $shown !== $value ? ' title="'
  . $template->escape($words->get('cell.term', ['term' => (string) $value]))
  . '"' : '' ?>><?= $template->escape($shown) ?></span>
<?php elseif (($kind->isReference() || $chips !== []) && !$many): ?>
<?= $chip($chips[0], $says[0] ?? '') ?>
<?php elseif ($kind->isReference()): ?>
<span class="chips"><?php foreach ($chips as $index => $each): ?><?= $chip($each,
  $says[$index] ?? '') ?><?php endforeach ?></span>
<?php elseif ($kind === FieldKind::Markdown):
  $plain = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[#*_>`]/u', '', $shown)));
  $links = preg_match_all('/\]\(skemik:[^)]+\)/u', $shown);
?>
<span class="cell-markdown"><span lang="<?= $template->escape($locale) ?>"><?= $template->escape(mb_strimwidth($plain,
  0, 60, '…')) ?></span><?php if ($links > 0): ?> <span
  class="cell-note">⇢ <?= $links ?></span><?php endif ?></span>
<?php elseif (in_array($kind, [FieldKind::Date, FieldKind::DateTime, FieldKind::DateOrTime, FieldKind::Duration],
  true)): ?>
<span title="<?= $template->escape((string) $value) ?>"><?= $template->escape($shown) ?></span>
<?php elseif (in_array($kind, [FieldKind::Text, FieldKind::LongText], true)): ?>
<span lang="<?= $template->escape($locale) ?>"><?= $template->escape($shown) ?></span>
<?php else: ?>
<?= $template->escape($shown) ?>
<?php endif ?>
