<?php

declare(strict_types=1);

/* What every address answers while an update or a restore is under way
   (`Support\Halt`): the failure page's shape, saying what is happening and never
   that something went wrong, with no way back to offer, since waiting is the way.
   The page opens its address again automatically once the wait is over
   (`view/scripts/app.js`), so nobody reloads into a half-moved house. */

if (!isset($template)) {
  return;
}
ob_start();
?>
<body class="failure">
<main class="failure-card" data-waiting>
  <span class="waiting" aria-hidden="true"></span>
  <h1><?= $template->escape($words->get('waiting.title')) ?></h1>
  <p><?= $template->escape($sentence) ?></p>
  <p><?= $template->escape($words->get('waiting.again')) ?></p>
</main>
<?= $template->render('partials/footer', ['words' => $words, 'ground' => true]) ?>
</body>
<?php
echo $template->render('partials/document', ['words' => $words, 'title' => $words->get('waiting.title'),
  'islands' => false, 'body' => (string) ob_get_clean()]);
