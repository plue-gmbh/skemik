<?php

declare(strict_types=1);

/* Above the view (`Admin\QueryBar`), one form whose fields are the address, and nothing that makes a
   record, since this changes only what is seen. A choice no row holds is not offered, and a field called
   what the Status group is called names its collection beside it. A choice adds to what narrows, so the
   select stays at its default, as quiet as the placeholder, and what narrows stands beside it as chips:
   another value of one field finds either, another field narrows further, as the feed reads them. A chip's
   link takes it away and names its field where the chip shows a value alone. Then the mark that saves the
   narrowing as a question of the home screen, or deletes the one it is, for whoever arranges the collection.
   Search matches what the feed's `?q=` matches, so its placeholder names the collection and not a field. n of
   total is announced, since typing redraws where a screen reader does not look. Handed `$columns`,
   `$collection`, `$filters`, `$named`, `$parameters`, `$narrowed`, `$narrowing`, `$fixed`, `$all`, `$total`,
   `$keeps`, `$question`, the saved one this address is or null, and `$words`. */

if (!isset($template)) {
  return;
}
$sort = (string) ($parameters['sort'] ?? '');
$sortField = ltrim($sort, '-');
$groups = [];
foreach ($filters as $filter) {
  $groups[$filter->group][] = $filter;
}
$search = $words->get('query.search', ['name' => $collection->name]);
$descending = str_starts_with($sort, '-');
/* A timeline's newest first is its default, as no sort is every other view's; a field chosen sorts descending
   first, so its direction stands out once turned. */
$sorted = $sortField !== '' && !($fixed && $descending);
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
<?php if ($groups !== [] || $named === []): ?>
  <select class="query-filter" aria-label="<?= $template->escape($words->get('query.filter')) ?>"
    data-query-choose>
    <?= $option('', $words->get($named === [] ? 'query.none' : 'query.more'), true) ?>
<?php foreach ($groups as $group => $members): ?>
    <optgroup label="<?= $template->escape($group) ?>">
<?php foreach ($members as $filter): ?>
      <?= $option($filter->pair(), $filter->word, false) ?>
<?php endforeach ?>
    </optgroup>
<?php endforeach ?>
  </select>
<?php endif ?>
<?php if ($named !== []): ?>
  <ul class="query-narrowed">
<?php foreach ($named as $each):
  $whole = $words->get('query.narrowed', ['field' => $each->field, 'values' => $each->value]) ?>
    <li<?= $each->worded ? ' title="' . $template->escape($each->field) . '"'
      : '' ?>><?= $template->escape($each->worded ? $each->value : $whole) ?><a
      href="<?= $template->escape($each->without) ?>"
      aria-label="<?= $template->escape($words->get('query.unnarrow', ['filter' => $whole]))
      ?>"><?= $template->render('partials/icon', ['name' => 'close']) ?></a></li>
<?php endforeach ?>
  </ul>
<?php endif ?>
<?php if ($keeps && $question !== null): $unkeep = $words->get('question.remove') ?>
  <button type="submit" class="tool-button is-kept" form="question-remove"
    title="<?= $template->escape($unkeep) ?>"><?= $template->render('partials/icon', ['name' => 'kept']) ?><span
    class="visually-hidden"><?= $template->escape($unkeep) ?></span></button>
<?php elseif ($keeps && $narrowed): $keep = $words->get('question.save') ?>
  <button type="button" class="tool-button" data-dialog="question-save" aria-haspopup="dialog"
    title="<?= $template->escape($keep) ?>"><?= $template->render('partials/icon', ['name' => 'keep']) ?><span
    class="visually-hidden"><?= $template->escape($keep) ?></span></button>
<?php endif ?>
  <input class="query-search" type="search" name="q" value="<?= $template->escape($parameters['q'] ?? '') ?>"
    placeholder="<?= $template->escape($search) ?>" aria-label="<?= $template->escape($search) ?>" autocomplete="off"
    data-query-search>
  <span class="query-sort"<?= $fixed ? ' title="' . $template->escape($words->get('query.dated')) . '"' : '' ?>>
    <?= $template->render('partials/icon', ['name' => 'sort']) ?>
    <select<?= $sorted ? ' class="is-on"' : '' ?>
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
    <button type="button" class="query-direction<?= $descending ? '' : ' is-on' ?>"
      aria-label="<?= $template->escape($direction) ?>"
      title="<?= $template->escape($direction) ?>" data-query-direction><?= $template->render('partials/icon',
      ['name' => $descending ? 'descending' : 'ascending']) ?></button>
<?php endif ?>
    <input type="hidden" name="sort" value="<?= $template->escape($sort) ?>" data-query-sort-value>
  </span>
  <span class="query-count" role="status" data-query-count><?= $narrowed ? $template->escape($words->get('query.count',
    ['n' => $words->number($total), 'total' => $words->number($all)])) : '' ?></span>
</form>
