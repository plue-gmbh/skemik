<?php

declare(strict_types=1);

namespace Http;

/* The caching policy in one place: a minute's `public` for a stranger's read, a
   revalidated `private` under a token, since a shared cache must never hold a
   scoped reading, `no-store` for the admin and the dump, and `Vary` on all of
   them, one address serving four formats and two readings. Sixty seconds is a
   constant and not a setting: long enough for a house's visitors to share a
   fetch, short enough that publishing and reloading shows it, and anything longer
   needs a purge. The validator is the hash of the document rendered, right rather
   than merely cheap, since a record goes live at its start without a write. It
   renders validators and `Preconditions` consumes them, so the two never both
   decide. */
final readonly class CacheHeaders
{
  /** @return array<string, string> */
  public function admin(): array
  {
    return ['Cache-Control' => 'no-store', 'Vary' => 'Accept, Authorization'];
  }

  /** @return array<string, string> */
  public function feed(bool $scoped): array
  {
    return ['Cache-Control' => $scoped ? 'private, max-age=0, must-revalidate' : 'public, max-age=60',
      'Vary' => 'Accept, Authorization'];
  }

  /* A file's address names bytes that never change, so only who may read it
     can: an hour in this reader's own cache, the feed's minute in a shared one.
     @return array<string, string> */
  public function asset(bool $anonymous): array
  {
    return ['Cache-Control' => $anonymous ? 'public, max-age=60' : 'private, max-age=3600',
      'Vary' => 'Cookie, Authorization'];
  }

  public function validator(string $document): string
  {
    return '"' . substr(hash('sha256', $document), 0, 32) . '"';
  }

  /* A caller may send several, and `*` means any — both are the same answer. A
     tag a compressing server rewrote, `W/` in front or `-gzip` behind, is the
     tag it was.
     @param string $validator the tag this answer carries */
  public function matches(?string $sent, string $validator): bool
  {
    $held = array_map(fn (string $tag): string => (string) preg_replace(['/^W\//', '/-(?:gzip|br|zstd|deflate)"$/'],
      ['', '"'], trim($tag)), explode(',', (string) $sent));

    return in_array('*', $held, true) || in_array($validator, $held, true);
  }
}
