<?php

declare(strict_types=1);

namespace Auth;

/* One message as both channels send it: the text a client without HTML shows and
   the `login/` floor holds, and the page beside it, as `multipart/alternative`.
   Both parts are quoted-printable, which writes `=` as `=3D`, so a boundary that
   begins with `=_` can never occur inside one and needs no randomness. The
   recipient's name is sent as encoded words whatever it holds, since a comma or
   a quote in a bare name splits one address into two. */
final readonly class Mail
{
  public function __construct(
    public string $name,
    public string $subject,
    public string $text,
    public string $html,
  ) {}

  public function recipient(string $address): string
  {
    $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $this->name);
    if ($name === '') {
      return '<' . $address . '>';
    }
    $words = array_map(fn (string $part): string => '=?UTF-8?B?' . base64_encode($part) . '?=',
      mb_str_split($name, 10));

    return implode(' ', $words) . ' <' . $address . '>';
  }

  public function contentType(): string
  {
    return sprintf('multipart/alternative; boundary="%s"', $this->boundary());
  }

  /* CRLF throughout, as the wire and `mail()` both expect. */
  public function body(): string
  {
    $boundary = $this->boundary();
    $part = fn (string $type, string $content): string => sprintf("--%s\r\nContent-Type: %s; charset=UTF-8"
      . "\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n%s\r\n", $boundary, $type,
      quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $content)));

    return $part('text/plain', $this->text) . $part('text/html', $this->html) . '--' . $boundary . "--\r\n";
  }

  private function boundary(): string
  {
    return '=_' . substr(hash('sha256', $this->text . $this->html), 0, 32);
  }
}
