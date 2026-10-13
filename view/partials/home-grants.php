<?php

declare(strict_types=1);

/* Grants, `manageTokens` only: the token register, the question what may
   write here asked the other way round and the only place a whole key is seen,
   so no settings screen lists keys. Only the owner sees it since every row of it
   is a revoke; what a key may do is no secret from the house, and each
   collection's badge shows it to everyone who opens the collection. Who writes
   in is a different question from where it can be read, which is why it is not
   a footer of an addresses table. Beneath the rows the set-up key is issued,
   through the badge's own form and route (`view/scripts/grants.js`,
   `Admin\TokenController`): a name, a date, four boxes that each become a
   rule on every collection, configuring ticked and creating, modifying and
   reading beside it, since a script that sets a house up brings its records
   in too, and the secret shown once. */

if (!isset($template)) {
  return;
}
$named = fn (string $sentence): string => str_replace($template->escape(Admin\Grants::NAME),
  '<b data-grant-name>' . $template->escape($words->get('grant.this_key')) . '</b>', $template->escape($sentence));
if ($entries === []) { ?>
      <p class="home-empty"><?= $template->escape($words->get('grant.register_none')) ?></p>
<?php } else { ?>
      <ul class="changes">
<?php foreach ($entries as $entry) { ?>
        <li data-token="<?= $template->escape($entry->token->id) ?>"><button type="button" class="change grant-row"
          data-dialog="grant-key-<?= $template->escape($entry->token->id) ?>" aria-haspopup="dialog"><span
          class="change-head"><span
          class="grant-key"><?= $template->render('partials/glyph', ['name' => 'key']) ?></span><span
          class="change-name"><?= $template->escape($entry->token->label) ?></span><?php
          if ($entry->replaced !== null) { ?><span
          class="record-row-note"><?= $template->escape($words->get('grant.replaced_flag')) ?></span><?php }
          ?></span><span class="change-line"><span class="change-what"
          title="<?= $template->escape($entry->line) ?>"><?= $template->escape($entry->line) ?></span></span><span
          class="change-line is-quiet"><span
          class="change-what"><?= $template->escape($entry->facts) ?></span></span></button>
<?= $template->render('partials/grant-dialog', ['words' => $words, 'entry' => $entry, 'until' => $until,
  'csrf' => $csrf]) ?>
        </li>
<?php } ?>
      </ul>
<?php } ?>
      <p class="home-more"><button type="button" class="tool-button is-wide" data-dialog="grant-setup"
        aria-haspopup="dialog"><?= $template->escape($words->get('grant.setup_new')) ?></button></p>
      <dialog class="dialog grant-dialog" id="grant-setup" aria-labelledby="grant-setup-title" data-grant-setup>
        <h2 id="grant-setup-title"><?= $template->escape($words->get('grant.setup')) ?></h2>
        <p class="popover-hint"><?= $template->escape($words->get('grant.setup_hint')) ?></p>
        <form class="grant-issue" data-grant-issue data-grant-add="<?= $template->escape($words->get('grant.add')) ?>">
          <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
          <input type="hidden" name="collection" value="<?= $template->escape(implode(',', $collections)) ?>">
          <input type="hidden" name="field" value="">
          <label class="grant-field"><?= $template->escape($words->get('grant.name')) ?><input class="popover-input"
            name="label" required autocomplete="off" data-grant-label></label>
          <fieldset class="grant-offers">
            <legend class="grant-legend"><?= $template->escape($words->get('grant.may')) ?></legend>
<?php foreach (['arrange', 'create', 'set', 'read'] as $capability) { ?>
            <label class="grant-offer"><input type="checkbox" name="capability"
              value="<?= $capability ?>"<?= $capability === 'arrange' ? ' checked'
              : '' ?>><span><b><?= $template->escape($words->get('grant.capability_'
              . $capability)) ?></b> · <?= $template->escape($words->get('grant.setup_offer_'
              . $capability)) ?></span></label>
<?php } ?>
            <label class="grant-offer is-under" hidden data-grant-unpublished><input type="checkbox" name="unpublished"
              value="1"><span><?= $template->escape($words->get('grant.unpublished_offer')) ?></span></label>
          </fieldset>
          <label class="grant-field"><?= $template->escape($words->get('grant.until')) ?><input class="popover-input"
            type="date" name="until" value="<?= $template->escape($until) ?>" required></label>
          <label class="grant-offer" hidden data-grant-replace><input type="checkbox" name="replace"
            value="1"><span><?= $template->escape($words->get('grant.replace')) ?></span></label>
          <p class="grant-error" role="alert" data-grant-error></p>
          <div class="dialog-actions">
            <button type="button" class="button-quiet"
              data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
            <button type="submit" class="button-primary"><?= $template->escape($words->get('grant.issue')) ?></button>
          </div>
        </form>
        <div class="grant-secret" data-grant-secret hidden>
          <p><?= $named($words->get('grant.secret', ['name' => Admin\Grants::NAME])) ?></p>
          <code data-grant-secret-value></code>
          <button type="button" class="button-primary"
            data-grant-done><?= $template->escape($words->get('grant.done')) ?></button>
        </div>
      </dialog>
