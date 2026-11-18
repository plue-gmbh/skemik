<?php

declare(strict_types=1);

namespace Admin;

/* The hour a line of Needs a look is mailed in (`Notices`): at most once, so a
   key writing six hundred records a minute sends one mail, and what arrives
   within the hour waits for it to be out. One file per line in the data folder,
   holding when the line was last mailed and whether something came since, and
   no setting, since a restored snapshot would bring back an hour long past. It
   is asked under a lock, since the records of one burst arrive together. A
   folder that takes no write mails nothing, as it logs nothing. */
final readonly class Arrivals
{
  private const HOUR = 3600;
  private const WAITING = ' waiting';

  public function __construct(private string $directory) {}

  /* A record came to stand on the line: whether its mail leaves now. */
  public function arrived(Overlooked $line): bool
  {
    return $this->taken($line, true);
  }

  /* Whether what came within the line's hour is mailed now, the hour being out. */
  public function waited(Overlooked $line): bool
  {
    return $this->taken($line, false);
  }

  /* The same asked without the lock and taking nothing, for every answer to ask. */
  public function due(Overlooked $line): bool
  {
    $path = $this->path($line);
    $held = is_file($path) ? (string) file_get_contents($path) : '';

    return str_ends_with($held, self::WAITING) && time() - (int) $held >= self::HOUR;
  }

  private function taken(Overlooked $line, bool $arrived): bool
  {
    $handle = is_dir($this->directory) && is_writable($this->directory) ? fopen($this->path($line), 'c+') : false;
    if ($handle === false) {
      return false;
    }
    try {
      $held = flock($handle, LOCK_EX) ? (string) stream_get_contents($handle) : null;
      $waiting = $arrived || str_ends_with((string) $held, self::WAITING);
      $due = $held !== null && $waiting && time() - (int) $held >= self::HOUR;
      if ($held !== null) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $due ? (string) time() : (int) $held . ($waiting ? self::WAITING : ''));
      }

      return $due;
    } finally {
      fclose($handle);
    }
  }

  private function path(Overlooked $line): string
  {
    return $this->directory . '/arrived.' . $line->value;
  }
}
