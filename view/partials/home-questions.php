<?php

declare(strict_types=1);

/* Saved questions (`Admin\QuestionController`), for everybody: the organisation's
   own figures, each line a narrowed collection with one figure over all it
   narrows to, counted under the reader's scope as the totals are, and opening
   that collection as narrowed. A line is saved on its collection's screen once
   narrowed, and taken away there, both by whoever arranges the collection. No arithmetic
   between two lines, which is where a spreadsheet starts. A line's tooltip is its
   whole title, which the card's width cuts. With none saved the card is not
   drawn. Handed `$lines` and `$words`. */

if (!isset($template)) {
  return;
}
?>
      <ul class="record-rows">
<?php foreach ($lines as $line) { ?>
        <li><a class="record-row" href="<?= $template->escape($line->href) ?>"
          title="<?= $template->escape($line->name) ?>"><span
          class="record-row-glyph"><?= $template->render('partials/glyph', ['name' => $line->glyph]) ?></span><span
          class="record-row-name"><?= $template->escape($line->name) ?></span><span
          class="record-row-note question-figure"><?= $template->escape($line->figure) ?></span></a></li>
<?php } ?>
      </ul>
