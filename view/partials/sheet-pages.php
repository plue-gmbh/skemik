<?php

declare(strict_types=1);

/* Where a view's records fill more than a page: n–m of total, and the pages
   either side, each link keeping the query it was drawn under, its page size too. */

if (!isset($template)) {
  return;
}
$offset = ($page - 1) * $perPage;
$last = max(1, (int) ceil($total / $perPage));
$address = fn (int $to): string => '?' . http_build_query(array_filter([...$parameters, 'page' => (string) $to],
  fn (?string $value): bool => $value !== null && $value !== ''));
if ($last === 1) {
  return;
}
?>
<nav class="sheet-pages" aria-label="<?= $template->escape($words->get('sheet.pages')) ?>">
  <span><?= $template->escape($words->get('sheet.count', ['from' => (string) min($total, $offset + 1),
    'to' => (string) min($total, $offset + $shown), 'total' => (string) $total])) ?></span>
<?php if ($page > 1): ?>
  <a href="<?= $template->escape($address($page - 1)) ?>"><?= $template->escape($words->get('sheet.previous')) ?></a>
<?php endif ?>
<?php if ($page < $last): ?>
  <a href="<?= $template->escape($address($page + 1)) ?>"><?= $template->escape($words->get('sheet.next')) ?></a>
<?php endif ?>
</nav>
