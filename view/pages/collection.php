<?php

declare(strict_types=1);

/* One collection (`Admin\CollectionController`), its controls placed by what they
   change: the header holds what changes the collection, its name, grant badge,
   fields and + New record, and the row under it what changes only what is seen,
   the query bar and the view switch. The shape line is a line and not the
   heading, since the schema.org address it leads with leaves the site. What a
   delete just trashed is offered back over the view, since the undo belongs where
   the delete lands. */

if (!isset($template)) {
  return;
}
$type = basename($collection->type);
$shown = $words->get('collection.fields_count', ['n' => (string) count($columns)]);
$letOut = $words->get('collection.public_count',
  ['n' => (string) count(array_filter($columns, fn (Admin\Column $column): bool => $column->feed))]);
$drawn = isset($badges['']) ? $template->render('partials/grant-badge', ['words' => $words, 'badge' => $badges[''],
  'collection' => $collection, 'csrf' => $csrf, 'back' => $back, 'until' => $until]) : '';
$add = $words->get(($uploads ?? false) ? 'media.elsewhere' : 'sheet.add');
$uploading = $uploads ?? false;
$switch = fn (bool $menu): string => $template->render('partials/view-switch', ['words' => $words,
  'view' => $view, 'parameters' => $parameters, 'menu' => $menu]);
$current = $words->get('collection.view_menu', ['view' => $words->get('view.' . $view->value)]);
?>
<div class="page">
  <header class="collection-head">
    <div class="collection-title">
      <h1>
        <span><?= $template->escape($collection->name) ?></span>
        <?= $drawn ?>
<?php if (!$public): ?>
        <span class="tag-never" title="<?= $template->escape($words->get('collection.never_reason',
          ['type' => $type])) ?>"><?= $template->escape($words->get('collection.never')) ?></span>
<?php endif ?>
      </h1>
      <p class="page-shape"><a href="https://schema.org/<?= $template->escape($type) ?>" target="_blank"
        rel="noreferrer" title="<?= $template->escape($words->get('collection.proof',
        ['type' => $type])) ?>">schema.org/<?= $template->escape($type) ?></a> · <button type="button"
        class="shape-fields" aria-haspopup="dialog" data-field-popover="fields"><?= $template->escape($shown) ?><span
        class="shape-public">, <?= $template->escape($letOut) ?></span></button></p>
    </div>
    <div class="collection-tools">
      <button type="button" class="tool-button view-menu" aria-haspopup="dialog" data-view-menu
        title="<?= $template->escape($current) ?>"><?= $template->render('partials/icon',
        ['name' => $view->value]) ?><span class="visually-hidden"><?= $template->escape($current) ?></span></button>
<?php if ($uploading): $upload = $words->get('media.upload_up_to', ['limit' => $limit]) ?>
      <label class="tool-button upload-button"
        title="<?= $template->escape($upload) ?>"><?= $template->render('partials/icon', ['name' => 'upload']) ?><span
        class="visually-hidden"><?= $template->escape($upload) ?></span><input type="file" name="file" form="upload"
        id="upload-file" class="visually-hidden" accept="<?= $template->escape(implode(',',
        array_keys(Profile\Seed\Media::FORMATS))) ?>" multiple></label>
<?php endif ?>
<?php if ($creates): ?>
      <button type="button" class="tool-button" data-add-row
        title="<?= $template->escape($add) ?>"><?= $template->render('partials/icon', ['name' => 'plus']) ?><span
        class="visually-hidden"><?= $template->escape($add) ?></span></button>
<?php endif ?>
    </div>
<?= $switch(true) ?>
<?= $template->render('partials/fields', ['words' => $words, 'collection' => $collection, 'columns' => $columns,
  'badges' => $badges, 'fields' => $fields, 'offers' => $offers, 'csrf' => $csrf]) ?>
<?php foreach ($columns as $column): ?>
<?= $template->render('partials/field-popover', ['words' => $words, 'collection' => $collection, 'column' => $column,
  'entry' => $entries[$column->field->key] ?? null, 'badges' => $badges, 'public' => $public, 'csrf' => $csrf,
  'back' => $back, 'until' => $until]) ?>
<?php endforeach ?>
  </header>
<?php if ($uploading): ?>
  <form id="upload" method="post" action="/media" enctype="multipart/form-data" data-upload
    data-failed="<?= $template->escape($words->get('media.lost', ['limit' => $limit])) ?>">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
  </form>
<?php endif ?>
<?php if ($fielded !== ''): ?>
  <p class="page-note is-done" role="status"><?= $template->escape($fielded) ?></p>
<?php endif ?>
<?php foreach ($uploaded ?? [] as $sentence): ?>
  <p class="page-note is-done" role="status"><?= $template->escape($sentence) ?></p>
<?php endforeach ?>
<?php foreach ($refused ?? [] as $sentence): ?>
  <p class="page-note is-refused" role="alert"><?= $template->escape($sentence) ?></p>
<?php endforeach ?>
<p class="page-note is-refused" role="alert" data-upload-failed hidden></p>
<p class="page-note upload-progress" data-upload-progress hidden><span
  id="upload-sending"><?= $template->escape($words->get('media.sending')) ?></span><progress max="1"
  aria-labelledby="upload-sending"></progress></p>
<?php if (count($trashed) === 1): ?>
  <form class="trashed" method="post"
    action="/c/<?= $template->escape($collection->slug->value) ?>/<?= $template->escape($trashed[0]->entity->id->value)
      ?>/restore" role="status">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <span><?= $template->escape($words->get('record.trashed', ['label' => $trashed[0]->label])) ?></span>
    <button type="submit" class="button-quiet"><?= $template->escape($words->get('record.restore')) ?></button>
  </form>
<?php elseif ($trashed !== []): ?>
  <form class="trashed" method="post" action="/c/<?= $template->escape($collection->slug->value) ?>/restore"
    role="status">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <input type="hidden" name="ids" value="<?= $template->escape(implode(',',
      array_map(fn (Admin\SheetRow $row): string => $row->entity->id->value, $trashed))) ?>">
    <input type="hidden" name="back" value="<?= $template->escape(http_build_query($parameters)) ?>">
    <span><?= $template->escape($words->get('record.trashed_many', ['n' => (string) count($trashed)])) ?></span>
    <button type="submit" class="button-quiet"><?= $template->escape($words->get('record.restore')) ?></button>
  </form>
<?php endif ?>
  <div class="query-row">
<?= $template->render('partials/query-bar', ['words' => $words, 'collection' => $collection, 'columns' => $columns,
  'filters' => $filters, 'parameters' => $parameters, 'narrowed' => $narrowed,
  'total' => $total, 'all' => $all, 'narrowing' => $narrowing, 'fixed' => $fixed]) ?>
<?= $switch(false) ?>
  </div>
  <div data-view>
<?= $template->render('partials/view-' . $view->value, ['words' => $words, 'collection' => $collection,
  'columns' => $columns, 'rows' => $rows, 'page' => $page, 'perPage' => $perPage, 'total' => $total,
  'narrowed' => $narrowed, 'parameters' => $parameters, 'creates' => $creates, 'badges' => $badges, 'data' => $data,
  'spine' => $spine, 'items' => $items, 'add' => $add, 'house' => $house, 'glance' => $glance,
  'timeline' => $timeline, 'uses' => $uses ?? [], 'uploads' => $uploads ?? false, 'limit' => $limit ?? '']) ?>
  </div>
<?php if ($trash !== null): ?>
  <p class="trash-link"><a
    href="/trash/<?= $template->escape($collection->slug->value) ?>"><?= $template->render('partials/icon',
    ['name' => 'trash']) ?><?= $template->escape($words->get('trash.open', ['n' => (string) $trash])) ?></a></p>
<?php endif ?>
<?php if ($labels !== []): ?>
  <datalist id="grant-labels">
<?php foreach ($labels as $label): ?>
    <option value="<?= $template->escape($label) ?>">
<?php endforeach ?>
  </datalist>
<?php endif ?>
</div>
