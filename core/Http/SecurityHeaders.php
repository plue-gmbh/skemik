<?php

declare(strict_types=1);

namespace Http;

/* Every answer's policy headers, chosen by the door a path opens before any
   route is asked, so a refusal carries them too. `'unsafe-eval'` is the price
   of no build step, Vue compiling its `x-template`s, and holdings reach an
   island only as JSON, so no record's text is ever compiled. The feed says
   `frame-ancestors *`, since a host's front adds `SAMEORIGIN` and a browser
   holding both obeys the policy. It answers any origin and never with
   credentials, since an anonymous reading is what `curl` fetches anyway, and
   the admin sends no CORS header, which keeps its cookie same-origin. No
   per-install origin allowlist: a wrong value stays invisible until a frontend
   breaks. */
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
      'X-Robots-Tag' => 'noindex', ...($request->https ? ['Strict-Transport-Security' => 'max-age=31536000'] : [])];
    $door = $request->path === '/feed' || str_starts_with($request->path, '/feed/')
      || str_starts_with($request->path, '/media/');
    if (!$door) {
      return [...$shared, 'Content-Security-Policy' => self::ADMIN, 'X-Frame-Options' => 'DENY'];
    }

    return [...$shared, 'Content-Security-Policy' => self::FEED, 'Access-Control-Allow-Origin' => '*',
      'Access-Control-Expose-Headers' => 'ETag, Location',
      'Access-Control-Allow-Methods' => 'GET, POST, PATCH, OPTIONS',
      'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-None-Match',
      'Access-Control-Max-Age' => '86400'];
  }
}
