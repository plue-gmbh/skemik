<?php

declare(strict_types=1);

namespace Http;

/* What a researcher or a scanner finds at `/.well-known/security.txt`, as RFC
   9116 words it. The file answers for the domain and not for the software, so
   one the operator wrote into the domain root is answered as written, and
   without it the contact `config.php` names stands before ours, which is the
   manufacturer's and never the site owner's. A route and no shipped file,
   since `Expires` is counted from the release's day: an install a year behind
   says of itself that it is stale. No `Canonical`, because behind a proxy an
   install does not know its own scheme, and no `Preferred-Languages`, because
   the house's are not ours to state. */
final readonly class SecurityText
{
  private const MANUFACTURER = 'mailto:security@skemik.org';

  public function __construct(private string $root, private ?string $contact, private string $released) {}

  public function answer(): Response
  {
    $written = $this->root . '/.well-known/security.txt';

    return new Response(200, is_file($written) ? (string) file_get_contents($written) : $this->text(),
      ['Content-Type' => 'text/plain; charset=utf-8']);
  }

  private function text(): string
  {
    $expires = (new \DateTimeImmutable($this->released, new \DateTimeZone('UTC')))->modify('+1 year');
    $house = trim((string) $this->contact);
    $lines = $house === '' ? [] : ['# Who runs this site.',
      'Contact: ' . (str_contains($house, ':') ? $house : 'mailto:' . $house)];

    return implode("\n", [...$lines,
      '# plue GmbH, the manufacturer of Skemik, the software this site keeps its data in, and not',
      '# the site\'s owner. Write in German or English.',
      'Contact: ' . self::MANUFACTURER,
      'Expires: ' . $expires->format('Y-m-d\TH:i:s\Z')]) . "\n";
  }
}
