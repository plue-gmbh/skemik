<?php

declare(strict_types=1);

/* The copyright line, on the paper of every admin screen and on the ground
   under the cards a visitor meets before logging in, where it is also what says
   what this is. On that ground it takes the chrome's material, or its ink would
   be the paper's on a dark floor. */

if (!isset($template)) {
  return;
}
$links = ['<a href="https://plue.tech" target="_blank" rel="noreferrer">plue GmbH</a>',
  '<a href="https://skemik.org" target="_blank" rel="noreferrer">Skemik</a>™'];
?>
<footer class="footer"<?= $ground ? ' data-material="chrome-deep"' : '' ?>><?= str_replace(['{company}', '{product}'],
  $links,
  $template->escape($words->get('footer.copyright', ['company' => '{company}', 'product' => '{product}']))) ?></footer>
