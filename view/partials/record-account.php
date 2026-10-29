<?php

declare(strict_types=1);

/* The record screen of a person an account logs in as: its login address, and
   why the record outlives no delete while the account stands. Whoever manages
   accounts is led on to the account's dialog on the Accounts card, since a
   role and scopes are set there and nowhere on the record. */

if (!isset($template)) {
  return;
}
if ($held === null) {
  return;
}
$picture = array_find($images, fn (Admin\Chip $chip): bool => $chip->preview !== null)?->preview;
?>
  <section class="record-card side-card" aria-labelledby="account-title">
    <h2 class="side-card-title" id="account-title"><?= $template->escape($words->get('record.account')) ?></h2>
    <p class="side-card-sub"><?= $template->escape($words->get('record.held', ['email' => $held->email])) ?></p>
<?php if ($manages): ?>
    <ul class="record-rows">
      <li><a class="record-row" href="/#account-<?= $template->escape($held->id) ?>"><?=
        trim($template->render('partials/avatar', ['name' => $name, 'picture' => $picture])) ?><span
        class="record-row-name"><?= $template->escape($words->get('account.edit', ['name' => $name])) ?></span></a></li>
    </ul>
<?php endif ?>
  </section>
