<?php

declare(strict_types=1);

/* Languages, `manageHouse` only (`Admin\LocaleController`): the house's locales,
   and a card on the overview rather than a settings page, since the language is
   half of every record's key and stands in every address. A row the card's full
   width, as Accounts' are, the default first, its badge in the accent and
   without a remove, since it stays until another is made the default; every
   other badge is the press that makes its locale the default. A row's remove that would be
   refused is drawn disabled with the refusal as its title, so the rule is read
   before it is hit, and both presses that change what an address says, the
   default and a removal, ask first. Adding one is rare, so its field waits
   behind New language, which gives way to it. */

if (!isset($template)) {
  return;
}
$said = fn (string $key, string $tag): string => $template->escape($words->get($key, ['tag' => $tag]));
?>
<?php if ($refused !== '') { ?>
      <p class="page-note is-refused" role="alert"><?= $template->escape($refused) ?></p>
<?php } ?>
      <ul class="changes">
<?php foreach ($rows as $index => $row) {
  $id = 'locale-' . $template->escape($row->tag);
  $short = mb_strtoupper(explode('-', $row->tag)[0]); ?>
        <li class="locale-row">
<?php if ($index === 0) { ?>
          <span class="locale-dot is-current" title="<?= $template->escape($words->get('locale.default')) ?>"><span
            aria-hidden="true"><?= $template->escape($short) ?></span><span
            class="visually-hidden"><?= $template->escape($words->get('locale.default')) ?></span></span>
<?php } else { ?>
          <button type="button" class="locale-dot is-choosable" data-dialog="<?= $id ?>-default" aria-haspopup="dialog"
            aria-label="<?= $said('locale.make_default_label', $row->tag) ?>"
            title="<?= $said('locale.make_default_label', $row->tag) ?>"><?= $template->escape($short) ?></button>
<?php } ?>
          <span class="locale-tag"><?= $template->escape($row->tag) ?></span>
<?php if ($index > 0 && $row->refusal === null) { ?>
          <button type="button" class="button-quiet is-danger" data-dialog="<?= $id ?>-remove" aria-haspopup="dialog"
            aria-label="<?= $said('locale.remove_label',
            $row->tag) ?>"><?= $template->escape($words->get('locale.remove')) ?></button>
<?php } elseif ($index > 0) { ?>
          <button type="button" class="button-quiet" aria-disabled="true"
            aria-label="<?= $said('locale.remove_label', $row->tag) ?>" title="<?= $template->escape($row->refusal) ?>"
            data-toast="<?= $template->escape($row->refusal) ?>"><?= $template->escape($words->get('locale.remove'))
              ?></button>
<?php } ?>
          <span class="locale-count"><?= $template->escape($row->count) ?></span>
<?php if ($index > 0) { ?>
          <dialog class="dialog" id="<?= $id ?>-default" aria-labelledby="<?= $id ?>-default-title">
            <form method="post" action="/settings/locales/order">
              <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
              <input type="hidden" name="tag" value="<?= $template->escape($row->tag) ?>">
              <h2 id="<?= $id ?>-default-title"><?= $said('locale.default_question', $row->tag) ?></h2>
              <p><?= $said('locale.default_says', $row->tag) ?></p>
              <div class="dialog-actions">
                <button type="button" class="button-quiet"
                  data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
                <button type="submit"
                  class="button-primary"><?= $template->escape($words->get('locale.default_confirm')) ?></button>
              </div>
            </form>
          </dialog>
<?php } ?>
<?php if ($index > 0 && $row->refusal === null) { ?>
          <dialog class="dialog" id="<?= $id ?>-remove" aria-labelledby="<?= $id ?>-remove-title">
            <form method="post" action="/settings/locales/<?= $template->escape(rawurlencode($row->tag)) ?>/remove">
              <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
              <h2 id="<?= $id ?>-remove-title"><?= $said('locale.remove_question', $row->tag) ?></h2>
              <p><?= $template->escape($words->get('locale.remove_says')) ?></p>
              <div class="dialog-actions">
                <button type="button" class="button-quiet"
                  data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
                <button type="submit"
                  class="button-danger"><?= $template->escape($words->get('locale.remove')) ?></button>
              </div>
            </form>
          </dialog>
<?php } ?>
        </li>
<?php } ?>
      </ul>
      <details class="locale-add">
        <summary class="tool-button is-wide"><?= $template->escape($words->get('locale.add')) ?></summary>
        <form method="post" action="/settings/locales">
          <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
          <input class="popover-input" name="tag" placeholder="fr-FR"
            aria-label="<?= $template->escape($words->get('locale.add_label')) ?>" autocomplete="off" spellcheck="false"
            required>
          <button type="submit"
            class="tool-button is-wide"><?= $template->escape($words->get('locale.add_confirm')) ?></button>
        </form>
      </details>
