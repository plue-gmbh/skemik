<?php

declare(strict_types=1);

namespace Auth;

/* A login code's delivery: the relay where one is configured, `mail()` where not, and
   `login/{account}.txt` on every send whatever the channel answered, and alone
   where the mail is withheld (`Admin\LoginController`). `mail()`
   returning true is not delivery, so a floor reached only on an admitted failure
   would never be reached where it matters; whoever can read `login/` can read
   `holdings/` beside it, so writing it always grants nothing new. One file per
   account, holding the latest send, which is the one the operator reading it
   has just asked for; a file left by a deleted account holds a spent code and
   an address. The code is never logged and never part of the answer. */
final readonly class CodeDelivery
{
  public function __construct(private string $loginDirectory, private ?Smtp $relay) {}

  public function send(Account $account, #[\SensitiveParameter] Mail $mail): void
  {
    $this->floor($account, $mail);
    if ($this->relay !== null) {
      $this->relay->send($account->email, $mail);

      return;
    }
    if (!function_exists('mail')) {
      return;
    }
    /* The host's own sender stays where `sendmail_from` names none, since a name
       needs an address beside it and the host decides that one. */
    $sender = (string) ini_get('sendmail_from');
    mail($mail->recipient($account->email), mb_encode_mimeheader($mail->subject, 'UTF-8', 'Q'),
      $mail->body(), [...($sender === '' ? [] : ['From' => 'Skemik <' . $sender . '>']),
        'MIME-Version' => '1.0', 'Content-Type' => $mail->contentType()]);
  }

  /* The floor holds the text part, which is the whole message a person needs. */
  public function floor(Account $account, #[\SensitiveParameter] Mail $mail): void
  {
    $this->writeFloor($account, $mail->subject . "\n\n" . $mail->text);
  }

  private function writeFloor(Account $account, #[\SensitiveParameter] string $text): void
  {
    $path = sprintf('%s/%s.txt', $this->loginDirectory, $account->id);
    if (!is_dir($this->loginDirectory) || !is_writable($this->loginDirectory)) {
      throw UnwritableDirectory::named($this->loginDirectory);
    }
    if (file_put_contents($path, $account->email . "\n\n" . $text . "\n", LOCK_EX) === false) {
      throw UnwritableDirectory::named($this->loginDirectory);
    }
    chmod($path, 0600);
  }
}
