<?php

declare(strict_types=1);

/* What a narrowed collection saves to the home screen (`Admin\QuestionController`): one figure, the count, a
   stored number summed, or that number on the record the collection's first date names last. The query
   goes as the address spells it, which the script reads again when it is sent, since typing a search moves
   the address a moment before this is drawn anew (`view/scripts/query-bar.js`). Handed `$collection`,
   `$columns`, `$parameters`, `$csrf` and `$words`. */

if (!isset($template)) {
  return;
}
$numbers = array_filter($columns, fn (Admin\Column $column): bool => $column->sums());
$date = array_find($columns, fn (Admin\Column $column): bool
  => in_array($column->field->kind, Storage\Range::DATES, true));
?>
<dialog class="dialog" id="question-save" aria-labelledby="question-save-title">
  <?= $template->render('partials/live-region') ?>
  <form method="post" action="/questions">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <input type="hidden" name="collection" value="<?= $template->escape($collection->slug->value) ?>">
    <input type="hidden" name="query" value="<?= $template->escape(http_build_query($parameters)) ?>"
      data-question-query>
    <h2 id="question-save-title"><?= $template->escape($words->get('question.save')) ?></h2>
    <p><?= $template->escape($words->get('question.says')) ?></p>
    <label class="spine-label" for="question-figure"><?= $template->escape($words->get('question.figure')) ?></label>
    <select class="popover-input" id="question-figure" name="figure">
      <option value="count"><?= $template->escape($words->get('question.count')) ?></option>
<?php foreach ($numbers as $column): ?>
      <option value="sum:<?= $template->escape($column->field->key) ?>"><?= $template->escape($words->get(
        'question.sum', ['field' => $column->label])) ?></option>
<?php endforeach ?>
<?php foreach ($date === null ? [] : $numbers as $column): ?>
      <option value="latest:<?= $template->escape($column->field->key) ?>"><?= $template->escape($words->get(
        'question.latest', ['field' => $column->label])) ?></option>
<?php endforeach ?>
    </select>
    <div class="dialog-actions">
      <button type="button" class="button-quiet"
        data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
      <button type="submit" class="button-primary"><?= $template->escape($words->get('question.confirm')) ?></button>
    </div>
  </form>
</dialog>
