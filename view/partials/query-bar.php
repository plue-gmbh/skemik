<?php

declare(strict_types=1);

/* Above the view (`Admin\QueryBar`), one form whose fields are the address. A
   filter value no row carries is not offered, and a field's group names its type
   beside it, since a field may be called Status too. Search matches what the
   feed's `?q=` matches, so its placeholder names the collection and not a field.
   A select at its default reads as quietly as the placeholder, so what narrows or
   orders the view stands out. n of total is announced, since typing redraws the
   view where a screen reader does not look. What makes a record stands in the
   header and not here, since this changes what is seen and nothing else. */

if (!isset($template)) {
  return;
}
$chosen = http_build_query(array_slice($narrowing, 0, 1, true));
$sort = (string) ($parameters['sort'] ?? '');
$sortField = ltrim($sort, '-');
$groups = [];
foreach ($filters as $filter) {
  $groups[$filter->group][] = $filter;
}
$offered = array_map(fn (Admin\Filter $filter): string => $filter->pair(), $filters);
$search = $words->get('query.search', ['name' => $collection->name]);
$descending = str_starts_with($sort, '-');
$direction = $words->get($descending ? 'query.descending' : 'query.ascending');
$dated = $fixed ? array_find($columns, fn (Admin\Column $column): bool => $column->parameter === $sortField) : null;
$option = fn (string $value, string $word, bool $on): string => sprintf('<option value="%s"%s>%s</option>',
  $template->escape($value), $on ? ' selected' : '', $template->escape($word));
?>
<form class="query-bar" method="get" role="search" aria-label="<?= $template->escape($words->get('query.label')) ?>"
  data-query-bar>
<?php foreach ($narrowing as $name => $value): ?>
  <input type="hidden" name="<?= $template->escape($name) ?>" value="<?= $template->escape($value) ?>"
    data-query-filter>
<?php endforeach ?>
  <select class="query-filter<?= $chosen === '' ? '' : ' is-on' ?>"
    aria-label="<?= $template->escape($words->get('query.filter')) ?>" data-query-choose>
    <?= $option('', $words->get('query.none'), $chosen === '') ?>
<?php if ($chosen !== '' && !in_array($chosen, $offered, true)): ?>
    <?= $option($chosen, urldecode($chosen), true) ?>
<?php endif ?>
<?php foreach ($groups as $group => $members): ?>
    <optgroup label="<?= $template->escape($group) ?>">
<?php foreach ($members as $filter): ?>
      <?= $option($filter->pair(), $filter->word, $filter->pair() === $chosen) ?>
<?php endforeach ?>
    </optgroup>
<?php endforeach ?>
  </select>
  <input class="query-search" type="search" name="q" value="<?= $template->escape($parameters['q'] ?? '') ?>"
    placeholder="<?= $template->escape($search) ?>" aria-label="<?= $template->escape($search) ?>" autocomplete="off"
    data-query-search>
  <span class="query-sort"<?= $fixed ? ' title="' . $template->escape($words->get('query.dated')) . '"' : '' ?>>
    <?= $template->render('partials/icon', ['name' => 'sort']) ?>
    <select<?= $sortField === '' ? '' : ' class="is-on"' ?>
      aria-label="<?= $template->escape($words->get('query.sort')) ?>"<?= $fixed
      && $dated === null ? ' disabled' : '' ?><?= $fixed ? ' data-whole' : '' ?> data-query-sort>
<?php if ($dated !== null): ?>
      <?= $option('-' . $sortField, $words->get('query.newest', ['field' => $dated->label]), $descending) ?>
      <?= $option($sortField, $words->get('query.oldest', ['field' => $dated->label]), !$descending) ?>
<?php else: ?>
      <?= $option('', $words->get('query.unsorted'), $sortField === '') ?>
<?php endif ?>
<?php foreach ($fixed ? [] : $columns as $column): ?>
<?php if ($column->sorts()): ?>
      <?= $option($column->parameter, $column->label, $column->parameter === $sortField) ?>
<?php endif ?>
<?php endforeach ?>
    </select>
<?php if ($sortField !== '' && !$fixed): ?>
    <button type="button" class="query-direction" aria-label="<?= $template->escape($direction) ?>"
      title="<?= $template->escape($direction) ?>" data-query-direction><?= $template->render('partials/icon',
      ['name' => $descending ? 'descending' : 'ascending']) ?></button>
<?php endif ?>
    <input type="hidden" name="sort" value="<?= $template->escape($sort) ?>" data-query-sort-value>
  </span>
  <span class="query-count" role="status" data-query-count><?= $narrowed ? $template->escape($words->get('query.count',
    ['n' => (string) $total, 'total' => (string) $all])) : '' ?></span>
</form>
