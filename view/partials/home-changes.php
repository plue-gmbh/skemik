<?php

declare(strict_types=1);

/* Last changed (`Admin\Changes`), for everybody: the latest action on each of
   seven records, read from the log and not from `updated_at`, with who took it,
   since what changed without who is the question half-answered. Who is the
   person's current name, so a colleague who renamed is not two people, and
   unknown only ever for a redacted name. A record the reader could not open is
   never named; the actor's name is not filtered, since who works in the house is
   no secret from the house. Two lines each at every width, so a short entry
   beside a long name is never cut. */

if (!isset($template)) {
  return;
}
if ($changes === []) { ?>
      <p class="home-empty"><?= $template->escape($words->get('change.none')) ?></p>
<?php } else { ?>
      <ul class="changes">
<?php foreach ($changes as $each) {
  $public = $each->row?->reach === Profile\Reach::Public;
  $state = $each->row === null ? null : ($public ? $words->get('status.' . $each->row->state) : $each->row->own);
  $mark = $template->render('partials/glyph', ['name' => $each->glyph]); ?>
        <li><a class="change" href="<?= $template->escape($each->href) ?>"><span
          class="change-head"><span class="record-row-glyph"><?php if ($state !== null) { ?><span
          class="state-dot <?= $public ? 'state-' . $template->escape((string) $each->row?->state) : 'state-own' ?>"
          title="<?= $template->escape($state) ?>"></span><span
          class="visually-hidden"><?= $template->escape($state) ?></span><?php } else { ?><?= $each->glyph === 'key'
          ? '<span class="grant-key">' . $mark . '</span>' : $mark ?><?php } ?></span><span
          class="change-name"><?= $template->escape($each->label) ?></span></span><span class="change-line"><span
          class="change-what"><?= strtr($template->escape($words->get('change.by', ['what' => $each->what,
          'who' => "\x01"])), ["\x01" => ($each->byKey ? '<span class="grant-key">'
          . $template->render('partials/glyph', ['name' => 'key']) . '</span>' : '')
          . $template->escape($each->who)]) ?></span><time class="change-when"
          datetime="<?= $template->escape($each->when) ?>"
          title="<?= $template->escape($each->stamp) ?>"><?= $template->escape($each->ago) ?></time></span></a></li>
<?php } ?>
      </ul>
<?php } ?>
<?php if ($log) { ?>
      <p class="home-more"><a class="tool-button is-wide"
        href="/changes"><?= $template->escape($words->get('change.all')) ?></a></p>
<?php } ?>
