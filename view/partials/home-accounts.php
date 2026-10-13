<?php

declare(strict_types=1);

/* Accounts, `manageAccounts` only (`Admin\AccountController`): who writes from
   inside, beside who writes from outside, and a card rather than a settings
   page, since who works in the house is a list an owner answers for like the
   grants beside it. A row per account names its role and scopes as a sentence
   and opens its dialog; New account opens the same dialog with a person to pick.
   An entry stands as Last changed's do, the full width of the card, under
   the picture or initials the header's account button shows. */

if (!isset($template)) {
  return;
}
$held = array_map(fn (Admin\Colleague $row): string => $row->account->person->value, $colleagues);
$avatar = function (Admin\Colleague $each) use ($template): string {
  $initials = implode('', array_map(fn (string $part): string => mb_substr($part, 0, 1),
    array_slice(preg_split('/\s+/u', trim($each->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'], 0, 2)));

  return $each->picture === null
    ? '<span class="avatar" aria-hidden="true">' . $template->escape(mb_strtoupper($initials)) . '</span>'
    : '<img class="avatar" src="' . $template->escape($each->picture) . '" alt="">';
};
$dialog = fn (?Admin\Colleague $each): string => $template->render('partials/account-dialog', ['words' => $words,
  'colleague' => $each, 'types' => $types, 'unreadable' => $unreadable, 'csrf' => $csrf, 'held' => $held]);
?>
      <ul class="changes">
<?php foreach ($colleagues as $each) { ?>
        <li data-account="<?= $template->escape($each->account->id) ?>"><button type="button" class="change account-row"
          data-dialog="account-<?= $template->escape($each->account->id) ?>" aria-haspopup="dialog"><span
          class="change-head"><?= $avatar($each) ?><span
          class="change-name"><?= $template->escape($each->name) ?></span><?php
          if ($each->account->disabledAt !== null) { ?><span
          class="account-flag"><?= $template->escape($words->get('account.disabled')) ?></span><?php } ?></span><span
          class="change-line"><span class="change-what"
          title="<?= $template->escape($each->sentence) ?>"><?= $template->escape($each->sentence) ?></span></span><span
          class="change-line is-quiet"><span
          class="change-what"><?= $template->escape($each->facts) ?></span></span></button>
<?= $dialog($each) ?>
        </li>
<?php } ?>
      </ul>
      <p class="home-more"><button type="button" class="tool-button is-wide" data-dialog="account-new"
        aria-haspopup="dialog"><?= $template->escape($words->get('account.new')) ?></button></p>
<?= $dialog(null) ?>
