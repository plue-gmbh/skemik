<?php

declare(strict_types=1);

/* A view's records as n–m of total, on one page too, so the count stands
   under every list that holds any; and where they fill more than a page, the
   pages either side, each link keeping the query it was drawn under, its page
   size too. A narrowed view adds what the whole collection holds, which a narrow
   screen reads here in place of the query bar's count, a line of its own there.
   Handed `$page`, `$perPage`, `$total`, `$shown`, `$parameters` and `$words`, and `$all`, null where
   nothing narrows. */

if (!isset($template)) {
  return;
}
$all ??= null;
$offset = ($page - 1) * $perPage;
$last = max(1, (int) ceil($total / $perPage));
$address = fn (int $to): string => '?' . http_build_query(array_filter([...$parameters, 'page' => (string) $to],
  fn (?string $value): bool => $value !== null && $value !== ''));
if ($total === 0) {
  return;
}
?>
<nav class="sheet-pages" aria-label="<?= $template->escape($words->get('sheet.pages')) ?>">
  <span><?= $template->escape($words->get('sheet.count', ['from' => $words->number(min($total, $offset + 1)),
    'to' => $words->number(min($total, $offset + $shown)), 'total' => $words->number($total)]))
    ?><?php if ($all !== null): ?><span class="sheet-pages-all"> · <?= $template->escape($words->get('sheet.count_all',
    ['all' => $words->number($all)])) ?></span><?php endif ?></span>
<?php if ($page > 1): ?>
  <a href="<?= $template->escape($address($page - 1)) ?>"><?= $template->escape($words->get('sheet.previous')) ?></a>
<?php endif ?>
<?php if ($page < $last): ?>
  <a href="<?= $template->escape($address($page + 1)) ?>"><?= $template->escape($words->get('sheet.next')) ?></a>
<?php endif ?>
</nav>
