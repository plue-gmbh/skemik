<?php

declare(strict_types=1);

namespace Http;

use Support\Json;

/* The admin as an app on a phone's home screen. A route and no shipped file,
   since its label is the app name `config.php` holds: two installs on one phone
   are told apart only by it, and Chrome's install dialog lets nobody edit it.
   Chrome fetches it without the session, so the name is readable by a stranger.
   The `id` stays `/`, or an app already installed would no longer be this one.
   A maskable icon at 192 beside the 512, since Android scaling the 512 down drew
   the mark half a pixel off centre. */
final readonly class WebManifest
{
  public function __construct(private ?string $name) {}

  public function answer(): Response
  {
    $name = $this->name ?? 'Skemik';

    return new Response(200, (new Json())->encode(['id' => '/', 'name' => $name, 'short_name' => $name,
      'start_url' => '/', 'display' => 'standalone', 'background_color' => '#161d19', 'icons' => [
        ['src' => '/view/icons/icon-maskable.png', 'sizes' => '512x512', 'type' => 'image/png',
          'purpose' => 'maskable'],
        ['src' => '/view/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png',
          'purpose' => 'maskable'],
        ['src' => '/view/icons/apple-touch-icon.png', 'sizes' => '180x180', 'type' => 'image/png',
          'purpose' => 'any'],
      ]]), ['Content-Type' => 'application/manifest+json']);
  }
}
