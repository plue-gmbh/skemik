<?php

declare(strict_types=1);

namespace Auth;

/* A mail leaving the install: through the relay `config.php` names, else the
   host's `mail()`, and nowhere where a host has switched that off and no relay
   is named. `mail()` returning true is not delivery, so its `false` is the only
   failure it can name. */
final readonly class Mailer
{
  public function __construct(private ?Smtp $relay) {}

  public function send(string $address, #[\SensitiveParameter] Mail $mail): void
  {
    if ($this->relay !== null) {
      $this->relay->send($address, $mail);

      return;
    }
    if (!function_exists('mail')) {
      return;
    }
    /* The host's own sender stays where `sendmail_from` names none, since a name
       needs an address beside it and the host decides that one. */
    $sender = (string) ini_get('sendmail_from');
    $sent = mail($mail->recipient($address), mb_encode_mimeheader($mail->subject, 'UTF-8', 'Q'), $mail->body(),
      [...($sender === '' ? [] : ['From' => 'Skemik <' . $sender . '>']), 'MIME-Version' => '1.0',
        'Content-Type' => $mail->contentType()]);
    if (!$sent) {
      throw new MailRefused('the host\'s mail() refused a mail');
    }
  }
}
