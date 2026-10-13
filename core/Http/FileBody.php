<?php

declare(strict_types=1);

namespace Http;

/* The bytes of a file a response sends, or the one range of them a `Range`
   header asked for — a video a browser cannot seek in is one Safari will not
   play at all. */
final readonly class FileBody
{
  public function __construct(public string $path, public int $offset, public int $length) {}
}
