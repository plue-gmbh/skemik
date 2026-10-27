<?php

declare(strict_types=1);

/* The login (`Admin\LoginController`): the chrome before the paper, one card,
   two steps. The sentence under the code field is the same whatever happened,
   and the house's own line, `config.php`'s, stands under the address step alone. */

if (!isset($template)) {
  return;
}
ob_start();
?>
<body class="login">
<main class="login-card">
  <?= $template->render('partials/mark', ['size' => 38]) ?>
  <h1 class="login-name">Skemik<span class="trademark">™</span></h1>
<?php if ($step === 'address'): ?>
  <form method="post" action="/login" class="login-form">
<?php if ($to !== null): ?>
    <input type="hidden" name="to" value="<?= $template->escape($to) ?>">
<?php endif ?>
    <label for="email"><?= $template->escape($words->get('login.address')) ?></label>
    <input id="email" name="email" type="email" autocomplete="email" required
      placeholder="<?= $template->escape($words->get('login.address_placeholder')) ?>">
    <button type="submit" class="button-primary"><?= $template->escape($words->get('login.ask')) ?></button>
  </form>
<?php else: ?>
  <form method="post" action="/login/code" class="login-form">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <label for="code"><?= $template->escape($words->get('login.code')) ?></label>
    <input id="code" name="code" type="text" autocomplete="one-time-code" inputmode="text"
      maxlength="12" required data-autofocus aria-describedby="login-sentence">
    <button type="submit" class="button-primary"><?= $template->escape($words->get('login.enter')) ?></button>
  </form>
  <a class="login-again" href="/login"><?= $template->escape($words->get('login.again')) ?></a>
<?php endif ?>
<?php if ($sentence !== null): ?>
  <p id="login-sentence" class="login-sentence" role="status"><?= $template->escape($sentence) ?></p>
<?php endif ?>
<?php if ($note !== null): ?>
  <p class="login-note"><?php if ($link !== null): ?><a href="<?= $template->escape($link) ?>"><?=
    $template->escape($note) ?></a><?php else: ?><?= $template->escape($note) ?><?php endif ?></p>
<?php endif ?>
</main>
<?= $template->render('partials/footer', ['words' => $words, 'ground' => true]) ?>
</body>
<?php
echo $template->render('partials/document', ['words' => $words, 'title' => $words->get('login.title'),
  'islands' => false, 'body' => (string) ob_get_clean()]);
