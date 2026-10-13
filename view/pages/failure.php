<?php

declare(strict_types=1);

/* What a visitor or an editor sees when a request fails (`Http\ErrorHandler`):
   the boot page's shape, one sentence and the way back, no trace. */

if (!isset($template)) {
  return;
}
ob_start();
?>
<body class="failure">
<main class="failure-card">
  <h1><?= $template->escape($words->get('failure.title')) ?></h1>
  <p><?= $template->escape($sentence) ?></p>
  <a href="/"><?= $template->escape($words->get('failure.home')) ?></a>
</main>
<?= $template->render('partials/footer', ['words' => $words, 'ground' => true]) ?>
</body>
<?php
echo $template->render('partials/document', ['words' => $words, 'title' => $words->get('failure.title'),
  'islands' => false, 'body' => (string) ob_get_clean()]);
