<?php

declare(strict_types=1);

namespace Http;

use Support\Clock;
use Support\Json;

/* Failures, one JSON line each in `log/app.log`, outside `holdings/` so a backup
   never carries it. Never a request body, which is the holdings in a file that is
   not, and never a login code, a token or an address; no requests, which the web
   server logs and which would hold visitors' addresses the house never asked to
   keep. A trace is written without its frames' arguments whatever
   `zend.exception_ignore_args` says, since an argument is where a login code or a
   relay password leaks. It rotates on the write that crosses 8 MB, there being no
   daemon, and compresses nothing, since a webspace may have no `gzip`. A line
   that cannot be written is lost rather than the response. */
final readonly class Logger
{
  public const FILE = 'app.log';
  private const ROTATE_BYTES = 8_000_000;
  public const KEEP = 5;

  public function __construct(private string $directory, private Clock $clock, private Json $json = new Json()) {}

  public function failure(string $incident, int $status, string $route, \Throwable $failure, ?string $actor): void
  {
    $this->write([
      'time' => $this->clock->now()->format(Clock::STORED),
      'incident' => $incident,
      'status' => $status,
      'route' => $route,
      'class' => $failure::class,
      'message' => $failure->getMessage(),
      'file' => $failure->getFile(),
      'line' => $failure->getLine(),
      'trace' => $this->trace($failure),
      'actor' => $actor,
    ]);
  }

  private function trace(\Throwable $failure): string
  {
    $lines = [];
    foreach ($failure->getTrace() as $index => $frame) {
      $lines[] = sprintf('#%d %s(%s): %s%s%s()', $index, $frame['file'] ?? '[internal]',
        $frame['line'] ?? '', $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']);
    }

    return implode("\n", $lines);
  }

  /** @param array<string, string|int|null> $entry */
  private function write(array $entry): void
  {
    $path = $this->directory . '/' . self::FILE;
    if (!is_dir($this->directory) || !is_writable($this->directory)) {
      return;
    }
    if (is_file($path) && filesize($path) > self::ROTATE_BYTES) {
      $this->rotate($path);
    }
    file_put_contents($path, $this->json->encode($entry) . "\n", FILE_APPEND | LOCK_EX);
  }

  private function rotate(string $path): void
  {
    for ($number = self::KEEP - 1; $number >= 1; $number--) {
      if (is_file($path . '.' . $number)) {
        rename($path . '.' . $number, $path . '.' . ($number + 1));
      }
    }
    rename($path, $path . '.1');
  }
}
