<?php

declare(strict_types=1);

/* The login code's mail as a page (`Admin\LoginMail`): the login screen's card on
   its dark ground, the code on the sunken surface, the footer under the card. The
   ground is set on the page and on the table that carries it, at the screen's
   full height, since a ground ending short of a phone's screen stood in a light
   inbox as a block of its own.
   Mail clients drop stylesheets and most of CSS, so every style is inline and
   the layout is tables; the mark is left out, since an SVG shows in few of them,
   and no font or image is fetched, which a client would block or report. */

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
<title><?= $template->escape($words->get('login.mail.subject', ['code' => $code])) ?></title>
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
            <p style="margin: 0 0 20px;"><?= str_replace('{host}', '<b>' . $template->escape($host) . '</b>',
              $template->escape($words->get('login.mail.lead', ['host' => '{host}']))) ?></p>
            <p style="margin: 0 0 20px; padding: 16px; background: #F3F6F2; border-radius: 10px; text-align: center;
              font-family: ui-monospace, Menlo, Consolas, 'Liberation Mono', monospace; font-size: 28px;
              font-weight: 700; letter-spacing: .18em; color: #131714;"><?= $template->escape($code) ?></p>
            <p style="margin: 0 0 12px;"><?= $template->escape($valid) ?></p>
            <p style="margin: 0; color: #5C635C; font-size: 13px;"><?= $template->escape($ignore) ?></p>
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
