<?php

declare(strict_types=1);

/* The right-hand column on every admin screen, what leaves the house beside the
   holdings, kept open across pages by a cookie. The address is the screen's and
   carries none of the admin's own parameters, so a status filter shows a draft
   on the left and the address lists what is published on the right, nor a sort
   or a filter on a field the house withholds, which the stranger is refused. The stranger
   is fetched from the address as any consumer fetches it, so a never-public
   collection shows its 404 here and never a reading nobody gets; the key reading
   what the colleague reads is fetched from `/feed-panel/…`, the same routes
   behind the session. */

if (!isset($template)) {
  return;
}
$switch = fn (string $label, string $marker, array $options): string => sprintf(
  '<div class="view-switch is-wide feed-switch" role="group" aria-label="%s" data-feed-%s>%s</div>',
  $template->escape($words->get($label)), $marker, implode('', array_map(fn (string $value, string $name): string
    => sprintf('<button type="button" aria-pressed="%s" data-%s="%s"%s>%s</button>',
      array_key_first($options) === $value ? 'true' : 'false', $marker, $template->escape($value),
      $value === 'keyed' ? ' title="' . $template->escape($words->get('feed.keyed_title')) . '"' : '',
      $template->escape($name)), array_keys($options), $options)));
?>
<aside id="feed-panel" class="feed-panel" data-material="terminal"
  aria-label="<?= $template->escape($words->get('header.feed')) ?>" data-feed-panel
  data-copied="<?= $template->escape($words->get('feed.copied')) ?>"
  data-failed="<?= $template->escape($words->get('feed.failed')) ?>"<?= $feed->open ? '' : ' hidden' ?>>
  <p class="feed-address">
    <span class="feed-method">GET</span>
    <a data-feed-address class="feed-link" href="<?= $template->escape($feed->address) ?>"
      title="<?= $template->escape($feed->address) ?>" target="_blank"
      rel="noreferrer"><?= $template->escape($feed->address) ?></a>
    <span class="feed-status" data-feed-status></span>
    <button type="button" class="feed-copy" title="<?= $template->escape($words->get('feed.copy')) ?>"
      data-feed-copy><?= $template->render('partials/icon', ['name' => 'copy']) ?><span
      class="visually-hidden"><?= $template->escape($words->get('feed.copy')) ?></span></button>
  </p>
  <div class="feed-switches">
<?php if ($feed->document): ?>
    <?= $switch('feed.formats', 'format', ['' => 'jsonld', '.html' => 'html', '.csv' => 'csv', '.md' => 'md']) ?>
<?php endif ?>
    <?= $switch('feed.readers', 'reader', ['anonymous' => $words->get('feed.anonymous'),
      'keyed' => $words->get('feed.keyed')]) ?>
  </div>
  <pre class="feed-document" tabindex="0" data-feed-document></pre>
</aside>
