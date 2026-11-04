<?php

declare(strict_types=1);

/* The general screen (`Admin\GeneralController`): one form of boxes by topic in
   three columns that each stack their own, as the overview's cards do, each
   legend naming where its values are kept, since that decides what a restore
   elsewhere brings. What is edited fills the first two; the third holds what
   nothing here changes, the address and the host's box, the largest upload and
   each cost `/install` named, a warning while it costs, each value under its
   label as a field's is. Where PHP cannot write `config.php` its boxes are
   disabled under one note saying so. A kept password shows only as dots. Every
   field keeps its label in sight and its hint tied to it, and a refusal is
   announced, since it arrives after a post. */

if (!isset($template)) {
  return;
}
$field = function (string $name, string $label, string $type = 'text', string $hint = '') use ($template, $values)
  : string {
  $described = $hint === '' ? '' : ' aria-describedby="' . $hint . '"';

  return '<div><label class="spine-label" for="general-' . $name . '">' . $template->escape($label) . '</label>'
    . '<input class="popover-input" id="general-' . $name . '" name="' . $name . '" type="' . $type . '"'
    . ' value="' . $template->escape($values[$name] ?? '') . '" spellcheck="false" autocomplete="off"' . $described
    . '></div>';
};
$legend = fn (string $title, string $store): string => '<legend>' . $template->escape($title)
  . ' <span class="settings-store">' . $template->escape($store) . '</span></legend>';
$machine = $writable ? '' : ' disabled aria-describedby="general-unwritable"';
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('general.title')) ?></h1>
    <p class="page-shape"><?= $template->escape($words->get('general.explain')) ?></p>
  </header>
<?php if ($notice !== null): ?>
  <p class="page-note is-done" role="status"><?= $template->escape($notice) ?></p>
<?php endif ?>
<?php if ($refusal !== null): ?>
  <p class="page-note is-refused" role="alert"><?= $template->escape($refusal) ?></p>
<?php endif ?>
<?php if (!$writable): ?>
  <p class="page-note" id="general-unwritable"><?= $template->escape($words->get('general.unwritable')) ?></p>
<?php endif ?>
  <form class="settings-form" method="post" action="/settings/general">
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <div class="settings-grid">
      <div class="settings-column">
        <fieldset>
          <?= $legend($words->get('general.time'), $words->get('general.database')) ?>
          <div><label class="spine-label" for="general-timezone"><?= $template->escape(
            $words->get('general.timezone')) ?></label>
          <select class="popover-input" id="general-timezone" name="timezone">
<?php foreach ($timezones as $zone): ?>
            <option<?= $zone === $values['timezone'] ? ' selected' : '' ?>><?= $template->escape($zone) ?></option>
<?php endforeach ?>
          </select></div>
          <div><label class="spine-label" for="general-language"><?= $template->escape(
            $words->get('general.language')) ?></label>
          <select class="popover-input" id="general-language" name="language" aria-describedby="general-language-hint">
<?php foreach ($languages as $code => $name): ?>
            <option value="<?= $template->escape($code) ?>"<?= $code === $values['language'] ? ' selected' : '' ?>
              ><?= $template->escape($name) ?></option>
<?php endforeach ?>
          </select>
          <p class="popover-hint" id="general-language-hint"><?= $template->escape(
            $words->get('general.language_hint')) ?></p></div>
        </fieldset>
        <fieldset<?= $machine ?>>
          <?= $legend($words->get('home.login_note'), 'config.php') ?>
          <?= $field('note', $words->get('general.note')) ?>
          <?= $field('link', $words->get('general.link'), 'url', 'general-link-hint') ?>
          <p class="popover-hint" id="general-link-hint"><?= $template->escape($words->get('general.link_hint')) ?></p>
        </fieldset>
      </div>
      <div class="settings-column">
        <fieldset class="settings-relay"<?= $machine ?>>
          <?= $legend($words->get('general.relay'), 'config.php') ?>
          <div class="settings-pair is-port">
            <?= $field('host', $words->get('general.host'), 'text', 'general-relay-hint') ?>
            <?= $field('port', $words->get('general.port'), 'number', 'general-relay-hint') ?>
          </div>
          <p class="popover-hint" id="general-relay-hint"><?= $template->escape($words->get('general.relay_hint'))
            ?></p>
          <div class="settings-pair">
            <?= $field('user', $words->get('general.user')) ?>
            <div><label class="spine-label" for="general-password"><?= $template->escape(
              $words->get('general.password')) ?></label>
            <input class="popover-input" id="general-password" name="password" type="password"
              autocomplete="new-password"<?= $kept ? ' placeholder="••••••••"' : '' ?>></div>
          </div>
          <?= $field('sender', $words->get('general.sender'), 'email') ?>
        </fieldset>
        <fieldset<?= $machine ?>>
          <?= $legend($words->get('home.contact'), 'config.php') ?>
          <?= $field('contact', $words->get('general.contact')) ?>
        </fieldset>
      </div>
      <div class="settings-column">
        <fieldset>
          <?= $legend($words->get('general.address'), 'config.php') ?>
          <p class="spine-label"><?= $template->escape($words->get('general.address_label')) ?></p>
          <p class="settings-fixed"><?= $template->escape($address) ?></p>
          <p class="popover-hint"><?= $template->escape($words->get('general.address_hint')) ?></p>
        </fieldset>
        <fieldset>
          <?= $legend($words->get('general.host_box'), 'php.ini') ?>
          <p class="spine-label"><?= $template->escape($words->get('general.upload')) ?></p>
          <p class="settings-fixed"><?= $template->escape($upload) ?></p>
          <p class="spine-label"><?= $template->escape($words->get('general.costs')) ?></p>
<?php if ($costs === []): ?>
          <p class="settings-fixed"><?= $template->escape($words->get('general.costs_none')) ?></p>
<?php else: ?>
          <ul class="settings-costs">
<?php foreach ($costs as $cost): ?>
            <li><span class="install-mark" aria-hidden="true">!</span><span><?= $template->escape($cost->sentence)
              ?></span></li>
<?php endforeach ?>
          </ul>
<?php endif ?>
          <p class="popover-hint"><?= $template->escape($words->get('general.host_hint')) ?></p>
        </fieldset>
      </div>
    </div>
    <div>
      <button type="submit" class="button-primary"><?= $template->escape($words->get('general.save')) ?></button>
    </div>
  </form>
</div>
