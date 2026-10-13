<?php

declare(strict_types=1);

namespace Update;

/* What the release server says, read and nothing else: one version, the newest,
   so a fix on an older line is never offered and the comparison stays
   `version_compare` on the machine; whether it is a security release; the day it
   is installed from, a staged rollout being the manifest's field and never a
   clock in the install, which one that says `early` in `config.php` does not
   wait for, so a release meets a few houses first; and where the archive and
   its signature are, on the manifest's own server: the manifest is not signed,
   so one changed on the way names no other server to pull from, and the
   signature is checked only once the bytes lie in `staging/`. */
final readonly class Manifest
{
  public function __construct(
    public string $version,
    public bool $security,
    public ?string $notBefore,
    public string $archive,
    public string $signature,
  ) {}

  /** @param array<string, mixed> $decoded */
  public static function read(array $decoded, string $address): self
  {
    foreach (['version', 'archive', 'signature'] as $key) {
      if (!is_string($decoded[$key] ?? null) || $decoded[$key] === '') {
        throw UpdateRefused::manifest('names no ' . $key);
      }
    }
    foreach (['archive', 'signature'] as $key) {
      if (self::origin($decoded[$key]) !== self::origin($address)) {
        throw UpdateRefused::manifest('names its ' . $key . ' on another server');
      }
    }
    $notBefore = $decoded['not_before'] ?? null;
    if ($notBefore !== null && (!is_string($notBefore) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $notBefore) !== 1)) {
      throw UpdateRefused::manifest('names no day under not_before');
    }

    return new self((string) $decoded['version'], (bool) ($decoded['security'] ?? false), $notBefore,
      (string) $decoded['archive'], (string) $decoded['signature']);
  }

  private static function origin(string $url): string
  {
    $parts = parse_url($url) ?: [];

    return strtolower(($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . ':' . ($parts['port'] ?? ''));
  }

  /* Newer than what runs, and not told to wait past today, which an install
     taking releases early is never told. */
  public function offered(string $running, string $today, bool $early): bool
  {
    return version_compare($this->version, $running, '>')
      && ($early || $this->notBefore === null || $this->notBefore <= $today);
  }
}
