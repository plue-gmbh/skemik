<?php

declare(strict_types=1);

/* The record screen's Languages card, on a work alone: one row per locale the install holds,
   the record's own marked by its row, each state by its dot, which a screen reader hears in words,
   every other with how much older or newer it is, a
   missing one offered to whoever writes, made or linked from a record already
   written in it, and a member let go where it is not the one the others name.
   Handed `$translations`, `$collection`, `$entity`, `$writes`, `$csrf` and `$words`. */

if (!isset($template)) {
  return;
}
$present = count(array_filter($translations, fn (Admin\Translation $each): bool => $each->record !== null));
$address = '/c/' . $collection->slug->value . '/' . $entity->id->value;
?>
  <section class="record-card side-card" aria-labelledby="languages-title">
    <h2 class="side-card-title" id="languages-title"><?= $template->escape($words->get('record.languages')) ?></h2>
    <p class="side-card-sub"><?= $template->escape($words->get('record.languages_count', ['n' => (string) $present,
      'total' => (string) count($translations)])) ?></p>
    <ul class="record-rows is-aligned">
<?php foreach ($translations as $each): $current = $each->record?->id->value === $entity->id->value ?>
<?php if ($each->record !== null): ?>
      <li class="record-row is-linked<?= $current ? ' is-current' : '' ?>"><span
        class="locale-dot<?= $current ? ' is-current' : '' ?>"><?= $template->escape($each->locale) ?></span><a
        class="record-row-name" href="<?= $template->escape((string) $each->href) ?>"<?= $current
        ? ' aria-current="page"' : '' ?>><?= $template->escape($each->label) ?></a>
<?php $state = $template->escape($words->get('status.' . $each->state)) ?>
        <span class="record-row-meta"><span class="record-row-mono">/<?= $template->escape(
          $each->record->slug->value) ?></span><?= $each->age === '' ? '' : '<span class="record-row-note">'
          . $template->escape($each->age) . '</span>' ?></span>
<?php if ($writes && $each->record->translationOf !== null): ?>
        <span class="record-row-acts"><form method="post" action="<?= $template->escape($address) ?>/unlink"><input
          type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>"><input type="hidden" name="record"
          value="<?= $template->escape($each->record->id->value) ?>"><button type="submit"
          class="button-quiet record-row-action" aria-label="<?= $template->escape($words->get(
          'record.translation_unlink_named', ['locale' => $each->locale])) ?>"><?=
          $template->escape($words->get('record.translation_unlink')) ?></button></form></span>
<?php endif ?>
        <span class="record-row-state"><span class="state-dot state-<?= $template->escape($each->state) ?>"
          title="<?= $state ?>"></span><span class="visually-hidden"><?= $state ?></span></span>
      </li>
<?php else: ?>
      <li><div class="record-row is-missing"><span
        class="locale-dot is-missing"><?= $template->escape($each->locale) ?></span><span
        class="record-row-name"><?= $template->escape($words->get('record.missing')) ?></span>
<?php if ($writes): ?>
        <span class="record-row-acts">
<?php if ($each->candidates !== []): ?>
        <details class="record-row-link"><summary class="button-quiet record-row-action"><?=
          $template->escape($words->get('record.translation_link')) ?></summary><form method="post"
          action="<?= $template->escape($address) ?>/link"><input type="hidden" name="_csrf"
          value="<?= $template->escape($csrf) ?>"><select name="record"
          aria-label="<?= $template->escape($words->get('record.translation_choice',
          ['locale' => $each->locale])) ?>">
<?php foreach ($each->candidates as $candidate): ?>
          <option value="<?= $template->escape($candidate->record?->id->value ?? '') ?>"><?=
            $template->escape($candidate->label . ' · /' . $candidate->record?->slug->value) ?></option>
<?php endforeach ?>
        </select><button type="submit" class="button-secondary"><?=
          $template->escape($words->get('record.translation_link')) ?></button></form></details>
<?php endif ?>
        <form method="post" action="<?= $template->escape($address) ?>/translation"><input type="hidden"
          name="_csrf" value="<?= $template->escape($csrf) ?>"><input type="hidden" name="locale"
          value="<?= $template->escape($each->locale) ?>"><button type="submit"
          class="button-quiet"><?= $template->escape($words->get('record.create_translation',
          ['locale' => $each->locale])) ?></button></form>
        </span>
<?php endif ?>
      </div></li>
<?php endif ?>
<?php endforeach ?>
    </ul>
  </section>
