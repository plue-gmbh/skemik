<?php

declare(strict_types=1);

/* A notification mail as a page (`Admin\Notices`), laid out as the login code's
   (`pages/login-mail.php`), whose reasons hold here: inline styles, tables, no
   mark, no font or image fetched. The link is the whole of what it offers, a
   button and its address beneath, for a client that shows no button.
   Handed `$greeting`, `$lead`, `$open`, `$link`, `$subject` and `$words`. */

if (!isset($template)) {
  return;
}
$sans = "font-family: Inter, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;";
$links = ['<a href="https://plue.tech" style="color: #9BA79F; text-decoration: underline;">plue GmbH</a>',
  '<a href="https://skemik.org" style="color: #9BA79F; text-decoration: underline;">Skemik</a>™'];
?>
<!doctype html>
<html lang="<?= $template->escape($words->language) ?>" style="height: 100%; background: #121815;">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= $template->escape($subject) ?></title>
</head>
<body style="height: 100%; min-height: 100vh; margin: 0; padding: 0; background: #121815;">
<table role="presentation" width="100%" height="100%" cellpadding="0" cellspacing="0" bgcolor="#121815"
  style="width: 100%; height: 100%; min-height: 100vh; background: #121815;">
  <tr>
    <td align="center" valign="top" style="padding: 40px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="max-width: 440px; background: #FFFFFF; border: 1px solid #E2E7E1; border-radius: 20px;">
        <tr>
          <td style="padding: 36px 32px 32px; <?= $sans ?> color: #131714; font-size: 15px; line-height: 1.55;">
            <p style="margin: 0 0 24px; font-size: 19px; font-weight: 700; letter-spacing: -.02em;">Skemik<span
              style="margin-left: .5px; font-size: .7em; font-weight: 500; vertical-align: .48em;">™</span></p>
            <p style="margin: 0 0 12px;"><?= $template->escape($greeting) ?></p>
            <p style="margin: 0 0 24px;"><?= $template->escape($lead) ?></p>
            <p style="margin: 0 0 16px;"><a href="<?= $template->escape($link) ?>" style="display: inline-block;
              padding: 10px 18px; background: #131714; border-radius: 10px; color: #FFFFFF; font-weight: 600;
              text-decoration: none;"><?= $template->escape($open) ?></a></p>
            <p style="margin: 0; color: #5C635C; font-size: 13px; word-break: break-all;"><?=
              $template->escape($link) ?></p>
          </td>
        </tr>
      </table>
      <p style="margin: 20px 0 0; <?= $sans ?> color: #9BA79F; font-size: 11px;"><?= str_replace(
        ['{company}', '{product}'], $links, $template->escape($words->get('footer.copyright',
        ['company' => '{company}', 'product' => '{product}']))) ?></p>
    </td>
  </tr>
</table>
</body>
</html>
