<?php

declare(strict_types=1);

/* One key's dialog on the Grants card (`view/scripts/grants.js`): who issued it,
   until when, when it was last used, and each of its rules as the badge words
   it. The name's newest key is re-issued here, its rules ticked as they stand
   and a stale one not, so renewing a key is one press and a rule left out on
   the way is the narrowing a live key cannot take. Its secret is shown once, as
   the badge shows it, and the dialog then closes only by reloading the page.
   Revoking asks once more, the confirm standing where the press was, since
   nothing takes it back and only a new secret handed to the integration
   replaces it; when the key was last used stands in the facts line. */

if (!isset($template)) {
  return;
}
$id = 'grant-key-' . $entry->token->id;
$named = fn (string $sentence): string => str_replace($template->escape(Admin\Grants::NAME),
  '<b>' . $template->escape($entry->token->label) . '</b>', $template->escape($sentence));
?>
<dialog class="dialog grant-dialog" id="<?= $template->escape($id) ?>"
  aria-labelledby="<?= $template->escape($id) ?>-title">
  <h2 id="<?= $template->escape($id) ?>-title"><?= $template->escape($words->get('grant.label',
    ['anchor' => $entry->token->label])) ?></h2>
  <p class="grant-facts"><?= $template->escape($entry->issued . ' · ' . $entry->facts) ?></p>
<?php if ($entry->replaced !== null): ?>
  <p class="grant-note"><?= $template->escape($entry->replaced) ?></p>
  <ul class="grant-others">
<?php foreach ($entry->rules as $rule => $sentence): ?>
    <li><?= $template->escape($sentence) ?><?php if (isset($entry->stale[$rule])): ?> <span
      class="grant-note is-stale"><?= $template->escape($entry->stale[$rule]) ?></span><?php endif ?></li>
<?php endforeach ?>
  </ul>
<?php else: ?>
  <form class="grant-issue" id="<?= $template->escape($id) ?>-reissue"
    data-grant-reissue="<?= $template->escape($entry->token->id) ?>">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <fieldset class="grant-offers">
      <legend class="spine-label"><?= $template->escape($words->get('grant.reissue_rules')) ?></legend>
<?php foreach ($entry->rules as $rule => $sentence): ?>
      <label class="grant-offer"><input type="checkbox" name="rule"
        value="<?= $template->escape($rule) ?>"<?= isset($entry->stale[$rule]) ? ''
        : ' checked' ?>><span><?= $template->escape($sentence) ?><?php if (isset($entry->stale[$rule])): ?><span
        class="grant-note is-stale"><?= $template->escape($entry->stale[$rule]) ?></span><?php endif ?></span></label>
<?php endforeach ?>
    </fieldset>
    <label class="grant-field"><?= $template->escape($words->get('grant.until')) ?><input class="popover-input"
      type="date" name="until" value="<?= $template->escape($until) ?>" required></label>
    <label class="grant-offer"><input type="checkbox" name="replace"
      value="1"><span><?= $template->escape($words->get('grant.replace')) ?></span></label>
    <p class="popover-hint"><?= $template->escape($words->get('grant.reissue_hint')) ?></p>
    <p class="grant-error" role="alert" data-grant-error></p>
  </form>
<?php endif ?>
  <div class="dialog-actions">
    <form class="grant-revoke" method="post"
      action="/settings/tokens/<?= $template->escape($entry->token->id) ?>/revoke" data-grant-revoking hidden>
      <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
      <button type="submit" class="button-danger"><?= $template->escape($words->get('grant.revoke_now')) ?></button>
    </form>
    <button type="button" class="button-quiet is-danger grant-revoke"
      data-grant-revoke-ask><?= $template->escape($words->get('grant.revoke')) ?></button>
    <button type="button" class="button-quiet"
      data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
<?php if ($entry->replaced === null): ?>
    <button type="submit" class="button-primary"
      form="<?= $template->escape($id) ?>-reissue"><?= $template->escape($words->get('grant.reissue')) ?></button>
<?php endif ?>
  </div>
<?php if ($entry->replaced === null): ?>
  <div class="grant-secret" data-grant-secret hidden>
    <p><?= $named($words->get('grant.secret', ['name' => Admin\Grants::NAME])) ?></p>
    <code data-grant-secret-value
      data-copied="<?= $template->escape($words->get('grant.copied')) ?>"></code>
    <button type="button" class="button-primary"
      data-grant-done><?= $template->escape($words->get('grant.done')) ?></button>
  </div>
<?php endif ?>
</dialog>
