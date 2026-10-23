<?php

declare(strict_types=1);

/* A grant anchor's reviews and issue form (`Admin\TokenController`). Everyone who
   sees the screen sees a live grant's review, a fact about the holdings, and
   `manageTokens` adds the form and take-it-back, not the information. The form is
   the grant as a sentence: a name the log will sign with, what it may do, one box
   per rule, so create and read are one key with one secret, and until when, since
   every token expires and a form without the date would lie. A name already live
   is answered with every rule the new secret then holds, the newest secret being
   the whole key. The secret is shown once, in a state an outside press does not
   close. Where nothing is granted yet and the form is offered, the popover opens
   on the form alone: a heading above it would say what the form already says. */

if (!isset($template)) {
  return;
}
$named = fn (string $sentence): string => str_replace($template->escape(Admin\Grants::NAME),
  '<b data-grant-name>' . $template->escape($words->get('grant.this_key')) . '</b>', $template->escape($sentence));
$anchor = 'grant-' . ($badge->field ?? 'collection') . '-';
$folded ??= false;
$bare = $badge->reviews === [] && $badge->offers !== [];
?>
<?php if (!$folded && !$bare): ?>
  <p class="popover-label"><?= $template->escape($words->get('grant.label', ['anchor' => $badge->title])) ?></p>
<?php endif ?>
<?php foreach ($badge->reviews as $review): ?>
  <div class="grant">
    <p class="grant-sentence"><span class="grant-key"><?= $template->render('partials/glyph', ['name' => 'key'])
      ?></span><?= $template->escape($review->sentence) ?></p>
    <p class="grant-facts"><?= $template->escape(implode(' · ', [$review->issued, $review->expires, $review->used,
      $review->writes])) ?></p>
<?php if ($review->replaced !== null): ?>
    <p class="grant-note"><?= $template->escape($review->replaced) ?></p>
<?php endif ?>
<?php if ($review->stale !== null): ?>
    <p class="grant-note is-stale"><?= $template->escape($review->stale) ?></p>
<?php endif ?>
<?php if ($review->others !== []): ?>
    <p class="grant-facts"><?= $template->escape($words->get('grant.others')) ?></p>
    <ul class="grant-others">
<?php foreach ($review->others as $other): ?>
      <li><?= $template->escape($other) ?></li>
<?php endforeach ?>
    </ul>
<?php endif ?>
<?php if ($badge->offers !== []): ?>
    <form method="post"
      action="/settings/tokens/<?= $template->escape($review->token->id) ?>/rules/<?= $template->escape(
        $review->rule->id) ?>/remove">
      <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
      <input type="hidden" name="back" value="<?= $template->escape($back) ?>">
      <button type="submit" class="button-quiet is-danger"><?= $template->escape($words->get($review->others === []
        ? 'grant.revoke' : 'grant.remove')) ?></button>
    </form>
<?php endif ?>
  </div>
<?php endforeach ?>
<?php if ($badge->offers !== []): ?>
  <form class="grant-issue<?= $bare ? ' is-first' : '' ?>" data-grant-issue
    data-grant-add="<?= $template->escape($words->get('grant.add')) ?>">
<?php if (!$folded): ?>
    <p class="popover-label"><?= $template->escape($words->get('grant.new')) ?></p>
<?php endif ?>
    <input type="hidden" name="_csrf" value="<?= $template->escape($csrf) ?>">
    <input type="hidden" name="collection" value="<?= $template->escape($collection->slug->value) ?>">
    <input type="hidden" name="field" value="<?= $template->escape($badge->field ?? '') ?>">
<?php if ($badge->subject !== null): ?>
    <input type="hidden" name="subject" value="<?= $template->escape($badge->subject) ?>">
<?php endif ?>
    <label class="grant-field"><?= $template->escape($words->get('grant.name')) ?><input class="popover-input"
      name="label" required autocomplete="off" list="grant-labels" data-grant-label></label>
    <fieldset class="grant-offers">
      <legend class="grant-legend"><?= $template->escape($words->get('grant.may')) ?></legend>
<?php foreach ($badge->offers as $index => $offer): ?>
      <label class="grant-offer"<?= $offer->note === null ? '' : ' title="' . $template->escape($offer->note)
        . '"' ?>><input type="checkbox" name="capability"
        value="<?= $template->escape($offer->capability->value) ?>"<?= $index === 0 ? ' checked'
        : '' ?><?= $offer->note === null ? '' : ' aria-describedby="' . $anchor . $offer->capability->value
        . '"' ?>><span><b><?= $template->escape($words->get('grant.capability_'
        . $offer->capability->value)) ?></b> · <?= $template->escape($offer->sentence)
        ?></span><?php if ($offer->note !== null): ?><span
        class="visually-hidden"
        id="<?= $anchor
        . $offer->capability->value ?>"><?= $template->escape($offer->note) ?></span><?php endif ?></label>
<?php if ($offer->unpublished !== null): ?>
      <label class="grant-offer is-under"<?= $index === 0 ? '' : ' hidden' ?> data-grant-unpublished><input
        type="checkbox" name="unpublished" value="1"><span><?= $template->escape($offer->unpublished) ?></span></label>
<?php endif ?>
<?php endforeach ?>
    </fieldset>
    <label class="grant-field"><?= $template->escape($words->get('grant.until')) ?><input class="popover-input"
      type="date" name="until" value="<?= $template->escape($until) ?>" required></label>
    <label class="grant-offer" hidden data-grant-replace><input type="checkbox" name="replace"
      value="1"><span><?= $template->escape($words->get('grant.replace')) ?></span></label>
    <p class="grant-error" role="alert" data-grant-error></p>
    <button type="submit" class="button-primary"><?= $template->escape($words->get('grant.issue')) ?></button>
  </form>
  <div class="grant-secret" data-grant-secret hidden>
    <p><?= $named($words->get('grant.secret', ['name' => Admin\Grants::NAME])) ?></p>
    <code data-grant-secret-value></code>
    <button type="button" class="button-primary"
      data-grant-done><?= $template->escape($words->get('grant.done')) ?></button>
  </div>
<?php endif ?>
