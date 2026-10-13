<?php

declare(strict_types=1);

namespace Http;

/* Every answer's policy headers, chosen by the door a path opens before any route
   is asked, so a refusal carries them as much as a document. `'unsafe-eval'` is
   the price of no build step, Vue compiling its `x-template`s, and holdings reach
   an island only as JSON, so no record's text is ever compiled. The feed may be
   framed by anyone, said as `frame-ancestors *` because a host's front adds
   `SAMEORIGIN` and a browser holding both obeys the policy. It answers any origin
   and never with credentials: an anonymous reading is what `curl` fetches anyway,
   and a token is sent only because the page put it there. The admin sends no
   CORS header, which keeps its cookie same-origin. A per-install origin allowlist
   is refused: a wrong value stays invisible until a frontend breaks in a browser. */
final readonly class SecurityHeaders
{
  private const ADMIN = "default-src 'none'; script-src 'self' 'unsafe-eval'; style-src 'self';"
    . " img-src 'self' data:; font-src 'self'; connect-src 'self'; manifest-src 'self'; form-action 'self';"
    . " base-uri 'none'; frame-ancestors 'none'";
  private const FEED = "default-src 'none'; style-src 'self'; img-src 'self' data: https:;"
    . " form-action 'none'; base-uri 'none'; frame-ancestors *";

  /** @return array<string, string> */
  public function for(Request $request): array
  {
    $shared = ['X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'same-origin',
      ...($request->https ? ['Strict-Transport-Security' => 'max-age=31536000'] : [])];
    $door = $request->path === '/feed' || str_starts_with($request->path, '/feed/')
      || str_starts_with($request->path, '/media/');
    if (!$door) {
      return [...$shared, 'Content-Security-Policy' => self::ADMIN, 'X-Frame-Options' => 'DENY',
        'X-Robots-Tag' => 'noindex'];
    }

    return [...$shared, 'Content-Security-Policy' => self::FEED, 'Access-Control-Allow-Origin' => '*',
      'Access-Control-Expose-Headers' => 'ETag, Location',
      'Access-Control-Allow-Methods' => 'GET, POST, PATCH, OPTIONS',
      'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-None-Match',
      'Access-Control-Max-Age' => '86400'];
  }
}
