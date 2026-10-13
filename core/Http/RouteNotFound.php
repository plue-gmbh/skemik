<?php

declare(strict_types=1);

namespace Http;

/* No route answers this method and path: a 404 before anything else runs. */
final class RouteNotFound extends \RuntimeException
{
  public static function for(string $method, string $path): self
  {
    return new self(sprintf('no route for %s %s', $method, $path));
  }
}
