<?php

declare(strict_types=1);

/* The first run (`Admin\InstallController`): the login's card, wider. The host's
   lines first and the form only once every one is met, so nobody fills in a form
   the server will refuse; met, they fold to one line, since a row of green ticks
   says nothing the form does not. Under them what slows the host and refuses
   nothing, shown only where it does. Every field keeps its label in sight, since
   a placeholder is gone once typed into, and from a value refilled after a
   refusal. A refusal is announced, since it arrives after a post. */

if (!isset($template)) {
  return;
}
ob_start();
?>
<body class="login">
<main class="login-card install-card">
  <?= $template->render('partials/mark', ['size' => 38]) ?>
  <h1 class="login-name"><?= $template->escape($words->get('install.title')) ?></h1>
  <p class="install-lead"><?= $template->escape($words->get('install.lead')) ?></p>
<?php if ($sentence !== null): ?>
  <p class="install-refusal" role="alert"><?= $template->escape($sentence) ?></p>
<?php endif ?>
  <details class="install-checks"<?= $ready && $costs === [] ? '' : ' open' ?>>
    <summary><?= $template->escape($words->get($ready ? 'install.checks_met' : 'install.checks_unmet',
      ['count' => (string) count($requirements)])) ?></summary>
  <ul class="install-requirements">
<?php foreach ($requirements as $requirement): ?>
    <li class="<?= $requirement->met ? 'install-met' : 'install-unmet' ?>">
      <span class="install-mark"><?= $template->render('partials/icon', ['name' => $requirement->met ? 'check'
        : 'close']) ?></span>
      <span><?= $template->escape($requirement->sentence) ?>
        <span class="visually-hidden">(<?= $template->escape($words->get($requirement->met
          ? 'install.met' : 'install.unmet')) ?>)</span></span>
    </li>
<?php endforeach ?>
<?php foreach ($costs as $cost): ?>
    <li class="install-costly">
      <span class="install-mark" aria-hidden="true">!</span>
      <span><?= $template->escape($cost->sentence) ?>
        <span class="visually-hidden">(<?= $template->escape($words->get('install.costs')) ?>)</span></span>
    </li>
<?php endforeach ?>
  </ul>
  </details>
<?php if ($ready): ?>
  <form method="post" action="/install" class="login-form">
<?php if ($asksFolder): ?>
    <h2 class="install-heading"><?= $template->escape($words->get('install.place')) ?></h2>
    <label for="folder"><?= $template->escape($words->get('install.folder')) ?></label>
    <input id="folder" name="folder" type="text" required data-autofocus spellcheck="false" autocapitalize="off"
      aria-describedby="folder-hint"
      value="<?= $template->escape($form['folder'] ?? '') ?>">
    <p id="folder-hint" class="install-note"><?= $template->escape($words->get('install.folder_hint')) ?></p>
<?php endif ?>
    <h2 class="install-heading"><?= $template->escape($words->get('install.owner')) ?></h2>
    <label for="name"><?= $template->escape($words->get('install.name')) ?></label>
    <input id="name" name="name" type="text" autocomplete="name" required<?= $asksFolder ? '' : ' data-autofocus' ?>
      value="<?= $template->escape($form['name'] ?? '') ?>">
    <label for="email"><?= $template->escape($words->get('install.email')) ?></label>
    <input id="email" name="email" type="email" autocomplete="email" required aria-describedby="email-hint"
      value="<?= $template->escape($form['email'] ?? '') ?>">
    <p id="email-hint" class="install-note"><?= $template->escape($words->get('install.email_hint')) ?></p>
    <h2 class="install-heading"><?= $template->escape($words->get('install.settings')) ?></h2>
    <label for="timezone"><?= $template->escape($words->get('install.timezone')) ?></label>
    <select id="timezone" name="timezone">
<?php foreach ($timezones as $zone): ?>
      <option<?= $zone === $form['timezone'] ? ' selected' : '' ?>><?= $template->escape($zone) ?></option>
<?php endforeach ?>
    </select>
    <label for="language"><?= $template->escape($words->get('install.language')) ?></label>
    <select id="language" name="language">
<?php foreach ($languages as $code => $name): ?>
      <option value="<?= $template->escape($code) ?>"<?= $code === $form['language'] ? ' selected' : '' ?>
        ><?= $template->escape($name) ?></option>
<?php endforeach ?>
    </select>
    <button type="submit" class="button-primary"><?= $template->escape($words->get('install.submit')) ?></button>
  </form>
<?php endif ?>
</main>
<?= $template->render('partials/footer', ['words' => $words, 'ground' => true]) ?>
</body>
<?php
echo $template->render('partials/document', ['words' => $words, 'title' => $words->get('install.title'),
  'islands' => false, 'body' => (string) ob_get_clean()]);
