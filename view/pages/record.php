<?php

declare(strict_types=1);

/* The record screen (`Admin\RecordController`): the collection's fields in the
   table's order, read and edited alike, so a field stays put when editing starts
   and arranging the table arranges this. Read is the default, editing entered on
   intent, and nothing is arranged per record — no blocks, no canvas, no layout of
   one's own: that is the step at which a CMS starts becoming the thing this
   replaces, refused here rather than resisted later. A field that is not prose
   opens the table's own editor (`partials/cell-editor`), one implementation and
   not two, and the type says which field is prose, so no install configures it.
   The words locale is `words` beside `in`, so a link and the reload after a save
   keep it; each option says Words, since on a type that also forks the Languages
   card offers a record of its own in the same locales. */

use Profile\FieldKind;

if (!isset($template)) {
  return;
}
$entity = $row->entity;
$shownIn = ($locale ?? $entity->locale)->tag;
$type = basename($collection->type);
$prefix = $public ? '/feed/' . $entity->locale->tag . '/' . $collection->slug->value . '/'
  : $collection->slug->value . '/';
[$before, $after] = $neighbours;
$preview = Admin\Rows::preview($entity);
$alt = trim((string) $entity->props->string('description'));
$format = (string) $entity->props->string('encoding_format');
$width = $entity->props->int('width');
$shape = implode(' · ', array_filter([$width === null ? '' : $width . '×' . $entity->props->int('height'), $format]));
$kind = function (Admin\Column $column) use ($chips, $prose, $naming): string {
  $key = $column->field->key;

  return match (true) {
    $key === $naming => '',
    $column->field->kind === FieldKind::Image => ($chips[$key] ?? []) === [] ? '' : 'figures',
    $column->prose() => isset($prose[$key]) ? 'prose' : '',
    default => 'lines',
  };
};
/* Each run of one kind inside a block is a part, so a value object's text reads as prose where it stands among
   the block's lines; the block's heading goes before its first lines. */
$parts = [];
foreach ($blocks as $block) {
  $runs = [];
  foreach ($block->columns as $column) {
    $part = $kind($column);
    if ($part === '') {
      continue;
    }
    if ($runs === [] || $runs[array_key_last($runs)][0] !== $part) {
      $runs[] = [$part, []];
    }
    $runs[array_key_last($runs)][1][] = $column;
  }
  $headed = false;
  foreach ($runs as [$part, $columns]) {
    $heads = $part === 'lines' && !$headed;
    $headed = $headed || $heads;
    $parts[] = ['kind' => $part, 'block' => new Admin\FieldBlock($block->heading, $columns), 'headed' => $heads];
  }
}
$joins = fn (int $index): bool => isset($parts[$index]) && $parts[$index]['block']->heading === null
  && $parts[$index]['kind'] === 'lines';
$back = '/c/' . $collection->slug->value . ($query === '' ? '' : '?' . $query);
$line = function (Admin\FieldBlock $block, Admin\Column $column) use ($template, $words, $row): string {
  $intern = $column->feed ? '' : ' <span class="tag-intern">' . $template->escape($words->get('record.intern'))
    . '</span>';

  return sprintf('<div class="record-line"><dt>%s%s</dt><dd>%s</dd></div>', $template->escape($block->labelOf($column)),
    $intern, $row->cells[$column->field->key]);
};
$tool = function (?string $href, string $icon, string $word, string $end) use ($template, $words): string {
  $name = $template->escape($words->get($word));
  $drawn = $template->render('partials/icon', ['name' => $icon]);

  return $href === null
    ? sprintf('<button type="button" class="tool-button" aria-disabled="true" title="%s" data-toast="%s">%s'
      . '<span class="visually-hidden">%s</span></button>',
      $name, $template->escape($words->get($end)), $drawn, $name)
    : sprintf('<a class="tool-button" href="%s" title="%s">%s<span class="visually-hidden">%s</span></a>',
      $template->escape($href), $name, $drawn, $name);
};
?>
<div class="page record-page" data-record-screen>
  <header class="collection-head">
    <div class="collection-title">
      <a class="record-back" href="<?= $template->escape($back) ?>"><?= $template->render('partials/icon',
        ['name' => 'back']) ?><?= $template->escape($collection->name) ?></a>
      <h1>
        <span><?= $template->escape($row->label) ?></span>
      </h1>
      <p class="record-spine"><?= $template->render('partials/spine-chip', ['words' => $words, 'row' => $row,
        'prefix' => $prefix, 'translations' => $translations, 'opens' => $writes]) ?><?php if (!$public): ?> <span
        class="tag-never"><?= $template->escape($words->get('record.private')) ?></span><?php endif ?></p>
    </div>
    <div class="collection-tools">
<?php if (count($offered) > 1): ?>
      <select class="words-select<?= $locale === null ? '' : ' is-on' ?>"
        aria-label="<?= $template->escape($words->get('record.words_in')) ?>"<?= $translations === null ? ''
        : ' title="' . $template->escape($words->get('record.words_same')) . '"' ?> data-words-select>
<?php foreach ($offered as $index => $each): $current = $each->tag === ($locale ?? $entity->locale)->tag ?>
        <option value="<?= $template->escape(Admin\Rows::address($collection, $entity,
          ltrim($query . ($index === 0 ? '' : '&words=' . rawurlencode($each->tag)),
          '&'))) ?>"<?= $current ? ' selected' : '' ?>><?= $template->escape($each->tag) ?></option>
<?php endforeach ?>
      </select>
<?php endif ?>
<?php if ($writes): ?>
      <div class="view-switch is-wide" role="group" aria-label="<?= $template->escape($words->get('record.mode')) ?>">
        <button type="button" aria-pressed="true" data-record-mode="read"><?= $template->render('partials/icon',
          ['name' => 'read']) ?><span
          class="view-switch-word"><?= $template->escape($words->get('record.read')) ?></span></button>
        <button type="button" aria-pressed="false" data-record-mode="edit"><?= $template->render('partials/icon',
          ['name' => 'edit']) ?><span
          class="view-switch-word"><?= $template->escape($words->get('record.edit')) ?></span></button>
      </div>
<?php endif ?>
<?php if ($writes && $asset): $replace = $words->get('media.replace', ['limit' => $limit]) ?>
      <label class="tool-button upload-button"
        title="<?= $template->escape($replace) ?>"><?= $template->render('partials/icon', ['name' => 'upload']) ?><span
        class="visually-hidden"><?= $template->escape($replace) ?></span><input type="file" name="file" form="replace"
        class="visually-hidden" accept="<?= $template->escape(implode(',',
        array_keys(Profile\Seed\Media::FORMATS))) ?>"></label>
<?php endif ?>
<?php if ($badge !== null): ?>
      <?= $template->render('partials/grant-badge', ['words' => $words, 'badge' => $badge, 'collection' => $collection,
        'csrf' => $csrf, 'back' => $back, 'until' => $until]) ?>
<?php endif ?>
      <?= $tool($before, 'previous', 'record.previous', 'record.first') ?>
      <?= $tool($after, 'next', 'record.next', 'record.last') ?>
<?php if ($writes && $held !== null): ?>
      <button type="button" class="tool-button" aria-disabled="true"
        title="<?= $template->escape($words->get('record.held', ['email' => $held->email])) ?>"
        data-toast="<?= $template->escape($words->get('record.held',
        ['email' => $held->email])) ?>"><?= $template->render('partials/icon', ['name' => 'trash']) ?><span
        class="visually-hidden"><?= $template->escape($words->get('record.held',
        ['email' => $held->email])) ?></span></button>
<?php elseif ($writes): ?>
      <button type="button" class="tool-button" title="<?= $template->escape($words->get('delete.confirm')) ?>"
        data-dialog="delete-<?= $template->escape($entity->id->value) ?>"><?= $template->render('partials/icon',
        ['name' => 'trash']) ?><span
        class="visually-hidden"><?= $template->escape($words->get('delete.confirm')) ?></span></button>
<?php endif ?>
    </div>
  </header>
<?php if ($writes && $asset): ?>
  <form id="replace" method="post"
    action="/c/<?= $template->escape($collection->slug->value) ?>/<?= $template->escape($entity->id->value) ?>/file"
    enctype="multipart/form-data" data-upload data-failed="<?= $template->escape($words->get('media.lost',
    ['limit' => $limit])) ?>">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
  </form>
  <p class="page-note is-refused" role="alert" data-upload-failed hidden></p>
  <p class="page-note upload-progress" data-upload-progress hidden><span
    id="upload-sending"><?= $template->escape($words->get('media.sending')) ?></span><progress max="1"
    aria-labelledby="upload-sending"></progress></p>
<?php endif ?>

  <article class="record-card" data-record-read>
<?php if ($asset): ?>
    <figure class="record-figure">
      <a class="record-image record-file"<?= $writes ? ' data-drop' : '' ?>
        href="<?= $template->escape((string) $entity->props->string('content_url')) ?>" target="_blank"
        rel="noreferrer"><?php if ($preview !== null): ?><img src="<?= $template->escape($preview) ?>" alt=""
        loading="lazy"><?php else: ?><?= $template->render('partials/glyph',
        ['name' => Admin\SidebarEntry::glyphOf($entity->type)]) ?><?php endif ?><span
        class="visually-hidden"><?= $template->escape($words->get('record.file_open')) ?></span></a>
      <figcaption><?= $template->escape($alt !== '' ? $alt
        : (string) $entity->props->string('name')) ?><?php if (str_starts_with($format,
        'image/') && $alt === ''): ?> <span
        class="no-alt"><?= $template->escape($words->get('record.no_alt')) ?></span><?php endif ?><?php
        if ($shape !== ''): ?><br><span class="record-file-shape"><?= $template->escape($shape) ?></span><?php
        endif ?></figcaption>
    </figure>
<?php endif ?>
<?php foreach ($parts as $index => ['kind' => $part, 'block' => $block, 'headed' => $headed]): ?>
<?php if ($part === 'figures'): foreach ($block->columns as $each): foreach ($chips[$each->field->key] as $chip): ?>
    <figure class="record-figure">
      <span class="record-image" aria-hidden="true"><?php if (!$chip->gone && $chip->preview !== null): ?><img
        src="<?= $template->escape($chip->preview) ?>" alt=""
        loading="lazy"><?php else: ?><?= $template->render('partials/glyph', ['name' => $chip->glyph])
        ?><?php endif ?></span>
      <figcaption><?= $template->escape($chip->gone ? $words->get('cell.gone') : ($chip->caption !== '' ? $chip->caption
        : $chip->label)) ?><?php if ($chip->noAlt): ?> <span
        class="no-alt"><?= $template->escape($words->get('record.no_alt')) ?></span><?php endif ?></figcaption>
    </figure>
<?php endforeach; endforeach; elseif ($part === 'prose'): foreach ($block->columns as $each): ?>
    <section class="record-prose">
      <h2 class="record-label"><?= $template->escape($each->label) ?></h2>
      <div lang="<?= $template->escape($shownIn) ?>"><?= $prose[$each->field->key] ?></div>
    </section>
<?php endforeach; else: ?>
<?php if ($headed && $block->heading !== null): ?>
    <h2 class="record-label record-block-heading"><?= $template->escape($block->heading) ?></h2>
<?php endif ?>
<?php if ($block->heading !== null || !$joins($index - 1)): ?>
    <dl class="record-fields<?= $block->heading === null ? '' : ' is-block' ?>">
<?php endif ?>
<?php foreach ($block->columns as $each): ?>
      <?= $line($block, $each) ?>
<?php endforeach ?>
<?php if ($block->heading !== null || !$joins($index + 1)): ?>
    </dl>
<?php endif ?>
<?php endif ?>
<?php endforeach ?>
  </article>

<?php if ($writes): ?>
  <div class="record-card record-form" data-record-edit data-sheet<?= $locale === null ? '' : ' data-words="'
    . $template->escape($locale->tag) . '"' ?> hidden>
<?php if ($locale !== null): ?>
    <p class="record-words-note"><?= $template->escape($words->get('record.words_note', ['locale' => $locale->tag,
      'authored' => $entity->locale->tag])) ?></p>
<?php endif ?>
    <div data-record="<?= $template->escape($entity->id->value) ?>">
<?php foreach ($editing as $block): ?>
<?php if ($block->heading !== null): ?>
      <h2 class="record-label record-block-heading"><?= $template->escape($block->heading) ?></h2>
<?php endif ?>
<?php foreach ($block->columns as $column): $key = $column->field->key; $kind = $column->field->kind->value ?>
<?php if ($column->editable && $column->prose()): $markdown = $column->field->kind === FieldKind::Markdown ?>
      <section class="record-prose-edit<?= $markdown ? ' is-markdown' : '' ?>"
        data-field="<?= $template->escape($key) ?>" id="edit-<?= $template->escape($key) ?>">
        <label class="record-label"
          for="prose-<?= $template->escape($key) ?>"><?= $template->escape($column->label) ?><?= $markdown
          ? ' · Markdown' : '' ?></label>
        <textarea id="prose-<?= $template->escape($key) ?>" class="record-textarea"
          lang="<?= $template->escape($shownIn) ?>" data-prose<?= $locale === null ? '' : ' placeholder="'
          . $template->escape((string) $authored->props->string($key))
          . '"' ?>><?= $template->escape((string) $edit->entity->props->string($key)) ?></textarea>
<?php if ($markdown): ?>
        <div class="record-prose-tools"><?php foreach (['heading' => 'data-mark="heading"',
          'bold' => 'data-mark="bold"', 'italic' => 'data-mark="italic"', 'quote' => 'data-mark="quote"',
          'code' => 'data-mark="code"', 'link' => 'data-link', 'link_insert' => 'data-link-insert']
          as $word => $marked): $icon = ['link' => 'open', 'link_insert' => 'link'][$word] ?? $word;
          $said = $template->escape($words->get('record.' . $word)) ?><button type="button" class="button-quiet"
          title="<?= $said ?>" <?= $marked ?>><?= $template->render('partials/icon', ['name' => $icon]) ?><span
          class="visually-hidden"><?= $said ?></span></button><?php endforeach ?></div>
        <div class="record-preview record-prose" lang="<?= $template->escape($shownIn) ?>" data-preview role="group"
          aria-label="<?= $template->escape($words->get('record.preview')) ?>"><?= $prose[$key] ?? '' ?></div>
<?php endif ?>
      </section>
<?php else: ?>
      <div class="record-line is-edit<?= $column->feed ? '' : ' is-withheld' ?>"
        id="edit-<?= $template->escape($key) ?>">
        <span class="record-edit-label"
          id="label-<?= $template->escape($key) ?>"><?= trim($template->render('partials/kind',
          ['kind' => $column->field->kind])) ?><?= $template->escape($block->labelOf($column)) ?></span>
<?php if ($column->editable && !$entity->keeps($key) && $column->field->kind->isReference()): ?>
        <span class="sheet-cell kind-cell-<?= $template->escape($kind) ?>"
          data-field="<?= $template->escape($key) ?>"<?= $column->kindMarks($edit->entity) ?>><span
          class="cell cell-held"><span class="cell-shown"><?= $edit->cells[$key] ?></span></span><button type="button"
          class="cell-edit" data-edit><span class="visually-hidden"><?= $template->escape($block->labelOf($column)
          . ': ' . $words->get('record.edit')) ?></span></button></span>
<?php elseif ($column->editable && !$entity->keeps($key)): ?>
        <span class="sheet-cell kind-cell-<?= $template->escape($kind) ?>"
          data-field="<?= $template->escape($key) ?>"<?= $column->kindMarks($edit->entity) ?>><button type="button"
          class="cell" data-edit aria-labelledby="label-<?= $template->escape($key) ?>"><span
          class="cell-shown"><?= $edit->cells[$key] ?></span></button></span>
<?php else: ?>
        <span class="sheet-cell kind-cell-<?= $template->escape($kind) ?>"<?= $column->kindMarks($edit->entity) ?>><span
          class="cell"><?= $edit->cells[$key] ?></span></span>
<?php endif ?>
      </div>
<?php endif ?>
<?php endforeach ?>
<?php endforeach ?>
    </div>
  </div>
<?php endif ?>

<?php if ($translations !== null): ?>
<?= $template->render('partials/record-languages', ['words' => $words, 'translations' => $translations,
  'entity' => $entity, 'collection' => $collection, 'writes' => $writes, 'csrf' => $csrf]) ?>
<?php endif ?>

<?= $template->render('partials/record-pointers', ['words' => $words, 'pointers' => $pointers,
  'held' => $writes ? $held : null]) ?>
<?php if ($writes && $held === null): ?>
<?= $template->render('partials/delete-confirm', ['words' => $words, 'collection' => $collection, 'row' => $row,
  'csrf' => $csrf, 'query' => $query, 'pointing' => count($pointers)]) ?>
<?php endif ?>
<?php if ($writes): ?>
<?= $template->render('partials/save-bar', ['words' => $words, 'data' => $data, 'spine' => $spine]) ?>
<?php endif ?>
<?php if ($badge !== null && $labels !== []): ?>
  <datalist id="grant-labels">
<?php foreach ($labels as $label): ?>
    <option value="<?= $template->escape($label) ?>">
<?php endforeach ?>
  </datalist>
<?php endif ?>
</div>
