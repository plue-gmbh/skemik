<?php

declare(strict_types=1);

namespace Admin;

/* One line of `log/app.log` as `Http\Logger::failure` wrote it. A key the line
   lacks reads empty, and a time that does not parse as none, rather than
   refusing the entry: a line from an older release or a hand-edited file is
   still a failure somebody may be asking about. */
final readonly class ErrorEntry
{
  public function __construct(
    public ?\DateTimeImmutable $time,
    public string $incident,
    public int $status,
    public string $route,
    public string $class,
    public string $message,
    public string $place,
    public string $trace,
  ) {}

  /** @param array<string, mixed> $line */
  public static function read(array $line): self
  {
    $text = fn (string $key): string => is_scalar($line[$key] ?? null) ? (string) $line[$key] : '';
    $place = $text('file') === '' ? '' : $text('file') . ':' . $text('line');

    return new self(self::time($text('time')), $text('incident'), (int) $text('status'), $text('route'), $text('class'),
      $text('message'), $place, $text('trace'));
  }

  private static function time(string $stored): ?\DateTimeImmutable
  {
    try {
      return $stored === '' ? null : new \DateTimeImmutable($stored);
    } catch (\DateMalformedStringException) {
      return null;
    }
  }
}
