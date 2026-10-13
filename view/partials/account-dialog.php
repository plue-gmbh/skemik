<?php

declare(strict_types=1);

/* One account's dialog on the Accounts card, or a new account's where no colleague
   is handed in (`view/scripts/accounts.js`). A new one starts at its person, picked
   through the search every edge uses or named new, so there is no second form
   for a name that already stands three collections away. Each scope editor is
   folded until asked for, since unscoped is the normal case for both and must
   not have to be dismissed to stay one: the write scope stands for an editor,
   the read scope for an editor and a viewer. The read scope's words say what it
   does, because the alternative is a house believing it hid something: a type
   left unticked is read as the public reads it, and a type never public says
   that this leaves nothing at all. The last owner is offered no
   way down, neither another role nor Disabled nor Delete account, and told why,
   since the repository would refuse each of them. */

if (!isset($template)) {
  return;
}
$account = $colleague?->account;
$id = 'account-' . ($account?->id ?? 'new');
$role = $account->role ?? Auth\Role::Editor;
$scopes = ['write' => $account?->write->types, 'read' => $account?->read->types];
$last = $colleague !== null && $colleague->lastOwner;
?>
<dialog class="dialog account-dialog" id="<?= $template->escape($id) ?>"
  aria-labelledby="<?= $template->escape($id) ?>-title">
  <form class="account-form" data-account-form="/settings/accounts<?= $account === null ? '' : '/'
    . $template->escape($account->id) ?>" data-method="<?= $account === null ? 'POST' : 'PATCH' ?>"
    data-csrf="<?= $template->escape($csrf) ?>"
    data-said-goes="<?= $template->escape($words->get('account.said_write_goes', ['types' => '{types}'])) ?>"
    data-said-comes="<?= $template->escape($words->get('account.said_read_comes', ['types' => '{types}'])) ?>"
    data-said-narrowed="<?= $template->escape($words->get('account.said_write_narrowed')) ?>">
    <h2 id="<?= $template->escape($id) ?>-title"><?= $template->escape($colleague === null ? $words->get('account.new')
      : $words->get('account.edit', ['name' => $colleague->name])) ?></h2>
<?php if ($colleague === null): ?>
    <div class="account-person" data-person data-held="<?= $template->escape(json_encode($held,
      JSON_THROW_ON_ERROR)) ?>" data-new-word="<?= $template->escape($words->get('account.person_new',
      ['name' => '{name}'])) ?>">
      <p class="spine-label" id="<?= $id ?>-person"><?= $template->escape($words->get('account.person')) ?></p>
      <p class="picker-current" data-person-chosen hidden><span class="chip"><span
        class="chip-mark"><?= $template->render('partials/glyph', ['name' => 'Person']) ?></span><span
        data-person-label></span></span><button type="button" class="picker-tool" data-person-change
        title="<?= $template->escape($words->get('account.person_change')) ?>"
        aria-label="<?= $template->escape($words->get('account.person_change'))
        ?>"><?= $template->render('partials/icon', ['name' => 'close']) ?></button></p>
      <div data-person-find>
        <input type="search" class="popover-input" data-person-search autocomplete="off"
          aria-labelledby="<?= $id ?>-person"
          placeholder="<?= $template->escape($words->get('account.person_search')) ?>">
        <div class="options" data-person-options></div>
      </div>
      <input type="hidden" name="person" value="">
      <input type="hidden" name="name" value="">
      <p class="popover-hint"><?= $template->escape($words->get('account.person_hint')) ?></p>
    </div>
<?php else: ?>
    <p><?= $template->escape($colleague->sentence) ?></p>
<?php endif ?>
    <label class="spine-label" for="<?= $id ?>-address"><?= $template->escape($words->get('account.email')) ?></label>
    <input class="popover-input" id="<?= $id ?>-address" type="email" name="email"
      value="<?= $template->escape($account->email ?? '') ?>" required autocomplete="off"
      aria-describedby="<?= $id ?>-email">
    <p class="popover-hint" id="<?= $id ?>-email"><?= $template->escape($words->get($account === null
      ? 'account.no_password' : 'account.email_hint')) ?></p>
    <fieldset class="grant-offers">
      <legend class="spine-label"><?= $template->escape($words->get('account.role')) ?></legend>
<?php foreach (Auth\Role::cases() as $each): ?>
      <label class="grant-offer"><input type="radio" name="role"
        value="<?= $each->value ?>"<?= $each === $role ? ' checked' : '' ?><?= $last
        && $each !== $role ? ' disabled' : '' ?>><span><b><?= $template->escape($words->get('account.role.'
        . $each->value)) ?></b><span class="grant-note"><?= $template->escape($words->get('account.about.'
        . $each->value)) ?></span></span></label>
<?php endforeach ?>
    </fieldset>
<?php foreach ($scopes as $scope => $narrowed): ?>
    <div class="account-scope" data-scope="<?= $scope ?>"<?= ($scope === 'write' ? $role !== Auth\Role::Editor
      : $role === Auth\Role::Owner) ? ' hidden' : '' ?>>
      <label class="grant-offer"><input type="checkbox" data-scope-narrow<?= $narrowed === null ? ''
        : ' checked' ?>><span><?= $template->escape($words->get('account.' . $scope . '_narrow')) ?></span></label>
<?php if ($scope === 'read'): ?>
      <p class="popover-hint"><?= $template->escape($words->get('account.read_hint')) ?></p>
<?php endif ?>
      <fieldset class="account-types" data-scope-types<?= $narrowed === null ? ' hidden' : '' ?>>
        <legend class="visually-hidden"><?= $template->escape($words->get('account.' . $scope . '_types')) ?></legend>
<?php foreach ($types as $type => $word): ?>
        <label class="account-type"><input type="checkbox" value="<?= $template->escape($type) ?>"<?= $narrowed === null
          || in_array($type, $narrowed, true) ? ' checked' : '' ?>><span><span
          data-type-word><?= $template->escape($word) ?></span><?php if ($scope === 'read' && in_array($type,
          $unreadable, true)): ?><span
          class="grant-note"><?= $template->escape($words->get('account.hidden')) ?></span><?php endif ?></span></label>
<?php endforeach ?>
      </fieldset>
    </div>
<?php endforeach ?>
    <p class="popover-hint" role="status" data-scope-said></p>
<?php if ($account !== null): ?>
    <label class="grant-offer"><input type="checkbox" name="disabled"
      value="1"<?= $account->disabledAt === null ? '' : ' checked' ?><?= $last ? ' disabled'
      : '' ?>><span><?= $template->escape($words->get('account.disable')) ?></span></label>
<?php if ($last): ?>
    <p class="popover-hint"><?= $template->escape($words->get('account.last_owner')) ?></p>
<?php else: ?>
    <div class="account-removing" data-account-removing hidden>
      <p><?= $template->escape($words->get('account.remove_question', ['name' => $colleague->name])) ?></p>
      <button type="button" class="button-danger"
        data-account-remove="/settings/accounts/<?= $template->escape($account->id) ?>/remove"><?= $template->escape(
          $words->get('account.remove_now')) ?></button>
    </div>
<?php endif ?>
<?php endif ?>
    <p class="grant-error" role="alert" data-account-error></p>
    <div class="dialog-actions">
<?php if ($account !== null && !$last): ?>
      <button type="button" class="button-quiet is-danger account-remove"
        data-account-remove-ask><?= $template->escape($words->get('account.remove')) ?></button>
<?php endif ?>
      <button type="button" class="button-quiet"
        data-dialog-close><?= $template->escape($words->get('dialog.cancel')) ?></button>
      <button type="submit" class="button-primary"><?= $template->escape($words->get($account === null
        ? 'account.create' : 'account.save')) ?></button>
    </div>
  </form>
</dialog>
