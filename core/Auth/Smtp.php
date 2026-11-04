<?php

declare(strict_types=1);

namespace Auth;


/* A minimal SMTP client, because hosting that disables `mail()` and hands out relay
   credentials runs a better mail setup, not a broken one — and the lines are
   cheaper than a dependency and its lockfile. Configured as `relay` in
   `config.php`, which belongs to the machine, so a copy of the holdings carries
   no credential. STARTTLS is required and a relay that refuses it is refused
   back, since the password would otherwise cross the wire in the clear; implicit
   TLS on 465 is not spoken. No retry and a timeout on every read. A failure is
   `RelayFailed` naming its fault (`RelayFault`), thrown after the answer has left
   and the `login/` floor was written, so a relay set wrong reaches no visitor and
   is a line of the error log, or by the save that proves a relay before keeping it
   (`probe`), which words the fault for the owner. The password is sent and
   forgotten: no log line or exception message carries it. */
final readonly class Smtp
{
  private const TIMEOUT_SECONDS = 10;
  private const TLS = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

  public function __construct(
    public string $host,
    public int $port,
    private string $user,
    #[\SensitiveParameter] private string $password,
    public string $sender,
    /* The install's own host, never the machine's name, which would tell the
       relay and every recipient the hoster's internal one. */
    private string $name,
  ) {}

  public function send(string $to, #[\SensitiveParameter] Mail $mail): void
  {
    $message = $this->message($to, $mail);
    $socket = $this->open();
    $this->deliver($socket, $to, $message);
    $this->command($socket, 'QUIT', 221);
  }

  /* Connected, encrypted and logged in, then gone: what a saved relay is asked before it is kept, sending
     nothing, so a sender the relay refuses is learnt at the first mail. */
  public function probe(): void
  {
    $this->command($this->open(), 'QUIT', 221);
  }

  /** @return resource */
  private function open()
  {
    $socket = stream_socket_client(sprintf('tcp://%s:%d', $this->host, $this->port), $number, $reason,
      self::TIMEOUT_SECONDS);
    if ($socket === false) {
      throw RelayFailed::because(RelayFault::Unreachable, sprintf('%s:%d (%s)', $this->host, $this->port, $reason));
    }
    stream_set_timeout($socket, self::TIMEOUT_SECONDS);
    $this->expect($socket, 220);
    if (!str_contains($this->command($socket, 'EHLO ' . $this->name, 250), 'STARTTLS')) {
      throw RelayFailed::because(RelayFault::NoStartTls);
    }
    $this->command($socket, 'STARTTLS', 220);
    if (stream_socket_enable_crypto($socket, true, self::TLS) !== true) {
      throw RelayFailed::because(RelayFault::Handshake);
    }
    $this->authenticate($socket, $this->command($socket, 'EHLO ' . $this->name, 250));

    return $socket;
  }

  /** @param resource $socket */
  private function authenticate($socket, string $extensions): void
  {
    try {
      if (preg_match('/^250[ -]AUTH\b.*\bPLAIN\b/mi', $extensions) === 1) {
        $credentials = base64_encode("\0" . $this->user . "\0" . $this->password);
        $this->command($socket, 'AUTH PLAIN ' . $credentials, 235);

        return;
      }
      $this->command($socket, 'AUTH LOGIN', 334);
      $this->command($socket, base64_encode($this->user), 334);
      $this->command($socket, base64_encode($this->password), 235);
    } catch (RelayFailed) {
      /* Its answer is not quoted: a server may word a refusal with what it was sent. */
      throw RelayFailed::because(RelayFault::Login);
    }
  }

  /** @param resource $socket */
  private function deliver($socket, string $to, string $message): void
  {
    $this->command($socket, sprintf('MAIL FROM:<%s>', $this->sender), 250);
    $this->command($socket, sprintf('RCPT TO:<%s>', $to), 250);
    $this->command($socket, 'DATA', 354);
    $stuffed = (string) preg_replace('/^\./m', '..', $message);
    $this->command($socket, $stuffed . "\r\n.", 250);
  }

  private function message(string $to, Mail $mail): string
  {
    foreach ([$to, $this->sender] as $address) {
      if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
        throw RelayFailed::because(RelayFault::Address, $address);
      }
    }
    $headers = ['Date: ' . date(DATE_RFC2822), 'From: Skemik <' . $this->sender . '>',
      'To: ' . $mail->recipient($to), 'Subject: ' . mb_encode_mimeheader($mail->subject, 'UTF-8', 'Q'),
      sprintf('Message-ID: <%s@%s>', bin2hex(random_bytes(12)), $this->name), 'MIME-Version: 1.0',
      'Content-Type: ' . $mail->contentType()];

    return implode("\r\n", $headers) . "\r\n\r\n" . $mail->body();
  }

  /** @param resource $socket */
  private function command($socket, string $line, int $expected): string
  {
    if (fwrite($socket, $line . "\r\n") === false) {
      throw RelayFailed::because(RelayFault::Closed);
    }

    return $this->expect($socket, $expected);
  }

  /** @param resource $socket */
  private function expect($socket, int $expected): string
  {
    $reply = '';
    do {
      $line = fgets($socket, 1024);
      if ($line === false) {
        throw RelayFailed::because(RelayFault::Timeout);
      }
      $reply .= $line;
    } while (($line[3] ?? ' ') === '-');
    if ((int) substr($line, 0, 3) !== $expected) {
      throw RelayFailed::because(RelayFault::Unexpected, trim($line));
    }

    return $reply;
  }
}
