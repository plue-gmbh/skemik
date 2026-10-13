<?php

declare(strict_types=1);

/* The overview (`Admin\HomeController`): the title, one line naming the house by
   the address it answers on, so a colleague with two installs open knows which
   this is, and its totals, and the cards in the order their contract argues,
   read across three columns that each stack their own, so a short card leaves
   no hole under it beside a long one; focus and a screen reader go down a
   column as the eye does. Too narrow for three, the cards flow down two
   balanced columns and then one in that same order, since a grid's rows would
   leave the hole again. The frame is the card's title and subtitle,
   the same for all six; what stands under them is the card's own partial. */

if (!isset($template)) {
  return;
}
$counted = fn (string $noun, int $n): string => $words->get('home.' . ($n === 1 ? 'one' : 'many') . '.' . $noun,
  ['n' => (string) $n]);
$columns = [];
foreach (array_keys($cards) as $index => $card) {
  $columns[$index % 3][$card] = $cards[$card];
}
?>
<div class="page">
  <header class="page-head">
    <h1><?= $template->escape($words->get('home.title')) ?></h1>
    <p class="page-shape"><a
      href="/"><?= $template->escape($address) ?></a> · <?= $template->escape($words->get('home.totals',
      ['records' => $counted('records', $records), 'collections' => $counted('collections', $collections)])) ?></p>
  </header>
  <div class="home-cards">
<?php foreach ($columns as $column) { ?>
    <div class="home-column">
<?php foreach ($column as $card => $values) { ?>
      <section class="record-card" aria-labelledby="home-<?= $card ?>">
        <h2 class="side-card-title" id="home-<?= $card ?>"><?= $template->escape($words->get('home.' . $card)) ?></h2>
        <p class="side-card-sub"><?= $template->escape($words->get('home.' . $card . '_sub')) ?></p>
<?= $template->render('partials/home-' . strtr($card, '_', '-'), ['words' => $words, ...$values]) ?>
      </section>
<?php } ?>
    </div>
<?php } ?>
  </div>
</div>
