<?php

declare(strict_types=1);

namespace Update;

/* minisign's prehashed signature, verified through `sodium` against the key in
   the source and nothing else: Ed25519 over the archive's Blake2b-512, read in
   chunks, and a second signature over the first and the trusted comment, which
   carries the version and the commit, so the file that names the commit is the
   signature itself. The key id in the signature must be the key's: a release
   signed with another key is refused before any byte of the archive is hashed.
   No override exists, not a flag, not a config key, not a constant. */
final readonly class Signature
{
  private const CHUNK = 1 << 20;
  private const TRUSTED = 'trusted comment: ';

  public function __construct(private string $key) {}

  /* The key's id as minisign prints it, a little-endian number, for the screen and a check by hand. */
  public function keyId(): string
  {
    return strtoupper(bin2hex(strrev($this->publicKey()[0])));
  }

  /* The trusted comment of a signature that holds, or a refusal naming the first thing wrong. */
  public function verify(string $archive, string $minisig): string
  {
    [$id, $public] = $this->publicKey();
    $lines = explode("\n", trim(str_replace("\r", '', $minisig)));
    if (count($lines) !== 4 || !str_starts_with($lines[2], self::TRUSTED)) {
      throw UpdateRefused::signature('is not a minisign signature');
    }
    [$signed, $global] = [base64_decode($lines[1], true), base64_decode($lines[3], true)];
    if ($signed === false || strlen($signed) !== 74 || $global === false || strlen($global) !== 64) {
      throw UpdateRefused::signature('is malformed');
    }
    if (substr($signed, 0, 2) !== 'ED') {
      throw UpdateRefused::signature('is not prehashed');
    }
    if (substr($signed, 2, 8) !== $id) {
      throw UpdateRefused::signature('is signed with another key');
    }
    [$signature, $trusted] = [substr($signed, 10), substr($lines[2], strlen(self::TRUSTED))];
    if (!sodium_crypto_sign_verify_detached($global, $signature . $trusted, $public)) {
      throw UpdateRefused::signature('does not verify over its trusted comment');
    }
    if (!sodium_crypto_sign_verify_detached($signature, $this->hashed($archive), $public)) {
      throw UpdateRefused::signature('does not verify over the archive');
    }

    return $trusted;
  }

  /** @return array{string, string} the key id and the public key, from the last line of the key */
  private function publicKey(): array
  {
    $lines = array_values(array_filter(explode("\n", trim($this->key))));
    $decoded = base64_decode($lines[count($lines) - 1] ?? '', true);
    if ($decoded === false || strlen($decoded) !== 42 || substr($decoded, 0, 2) !== 'Ed') {
      throw UpdateRefused::signature('cannot be checked: the key in the source is not a minisign key');
    }

    return [substr($decoded, 2, 8), substr($decoded, 10)];
  }

  private function hashed(string $path): string
  {
    $file = fopen($path, 'r') ?: throw UpdateRefused::because('the archive could not be read');
    $state = sodium_crypto_generichash_init('', 64);
    while (!feof($file)) {
      sodium_crypto_generichash_update($state, (string) fread($file, self::CHUNK));
    }
    fclose($file);

    return sodium_crypto_generichash_final($state, 64);
  }
}
