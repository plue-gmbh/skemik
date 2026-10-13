<?php

declare(strict_types=1);

namespace Admin;

use Auth\LoginCode;
use Auth\Mail;
use Support\Template;
use Support\Words;

/* The login code's mail, in the language of the browser that asked. It names the
   person and the host it logs into, since one mailbox can hold codes for several
   houses and a code asked for by nobody should say whose door was knocked on.
   The code is in the subject too, where a phone's notification shows it. */
final readonly class LoginMail
{
  public function __construct(
    private Template $template,
    private Words $words,
    private string $name,
    private string $origin,
  ) {}

  public function with(#[\SensitiveParameter] string $code): Mail
  {
    $host = (string) preg_replace('#^https?://#', '', $this->origin);
    $lines = [
      'greeting' => $this->name === '' ? $this->words->get('login.mail.greeting_unnamed')
        : $this->words->get('login.mail.greeting', ['name' => $this->name]),
      'valid' => $this->words->get('login.mail.valid', ['minutes' => (string) LoginCode::MINUTES]),
      'ignore' => $this->words->get('login.mail.ignore'),
    ];
    $copyright = $this->words->get('footer.copyright', ['company' => 'plue GmbH', 'product' => 'Skemik™']);
    $text = sprintf("%s\n\n%s\n\n    %s\n\n%s\n\n%s\n\n-- \n%s\nhttps://skemik.org\n", $lines['greeting'],
      $this->words->get('login.mail.lead', ['host' => $host]), $code, $lines['valid'], $lines['ignore'],
      $copyright);
    $html = $this->template->render('pages/login-mail',
      [...$lines, 'words' => $this->words, 'code' => $code, 'host' => $host]);

    return new Mail($this->name, $this->words->get('login.mail.subject', ['code' => $code]), $text, $html);
  }
}
