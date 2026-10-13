<?php

declare(strict_types=1);

namespace Admin;

use Support\Clock;
use Support\Fetch;
use Support\FetchFailed;
use Support\Halt;
use Support\Words;
use Update\Restore;
use Update\UpdateCheck;
use Update\Version;

/* What a hoster can change under a running install and a request cannot see for
   free, asked again of what `/install` proved: that the data folder and
   `sessions/` take a write and that a folder inside the domain root is still not
   served. Behind a marker that lasts an hour, stamped with PHP's version and
   ours, so an update or another PHP asks at once. A failed probe leaves no
   marker, so a fix shows on the next request. Served means the marker's own text
   came back, since a host that cannot reach itself says nothing about the folder.
   Free space halts nothing, reads still working: it is a line in the admin, as
   is a release that cannot be installed by itself (`Update\UpdateCheck`), the
   header's mark on every page. A restore under way halts every request until
   it is done (`Update\Restore`). */
final readonly class Probes
{
  private const MARKER = 'probed.txt';
  private const SECONDS = 3600;

  public function __construct(
    private string $data,
    private DataLayout $layout,
    private Fetch $fetch,
    private Clock $clock,
    private Words $words,
    private UpdateCheck $updates,
  ) {}

  /* The one thing to fix, or a restore to wait out, or null where every probe holds. */
  public function halt(string $origin): ?Halt
  {
    if ($this->restoring()) {
      return new Halt($this->words->get('boot.restoring'), passes: true);
    }
    if ($this->fresh()) {
      return null;
    }
    if (!$this->takes($this->data . '/' . self::MARKER)) {
      return new Halt($this->words->get('boot.data'));
    }
    /* Named per request: two probing at once, as a page and its thumbnails may, would
       each remove the other's and one halt naming sessions/. */
    $sessions = $this->data . '/sessions/' . bin2hex(random_bytes(6)) . '.' . self::MARKER;
    $failed = match (true) {
      !$this->takes($sessions) || !unlink($sessions) => 'boot.sessions',
      $this->served($origin) => 'boot.served',
      default => null,
    };
    if ($failed !== null) {
      unlink($this->data . '/' . self::MARKER);
    }

    return $failed === null ? null : new Halt($this->words->get($failed));
  }

  /* Read on every admin screen, since a disk fills between two hours as easily as within one. */
  public function space(): string
  {
    $free = DataLayout::free($this->data);

    return $free === null || $free >= DataLayout::FREE_BYTES ? ''
      : $this->words->get('frame.space', ['free' => (string) intdiv($free, 1_000_000)]);
  }

  /* The sentence behind the header's mark, or null while every release could be installed. */
  public function update(): ?string
  {
    $refused = $this->updates->report()->refused;

    return $refused === null ? null : $this->words->get('header.update_blocked', ['version' => $refused]);
  }

  /* A marker older than a restore can take was left by one that died, and leaves. */
  private function restoring(): bool
  {
    $marker = $this->data . '/' . Restore::MARKER;
    $written = is_file($marker) ? filemtime($marker) : false;
    if ($written === false) {
      return false;
    }
    if ($this->clock->now()->getTimestamp() - $written < Restore::SECONDS) {
      return true;
    }
    unlink($marker);

    return false;
  }

  private function fresh(): bool
  {
    $marker = $this->data . '/' . self::MARKER;
    $written = is_file($marker) ? filemtime($marker) : false;

    return $written !== false && $this->clock->now()->getTimestamp() - $written < self::SECONDS
      && file_get_contents($marker) === $this->stamp();
  }

  private function takes(string $path): bool
  {
    return file_put_contents($path, $this->stamp()) === strlen($this->stamp());
  }

  private function served(string $origin): bool
  {
    $path = $this->layout->servedPath($this->data);
    try {
      return $path !== null
        && $this->fetch->get($origin . $path . '/' . self::MARKER)->body === $this->stamp();
    } catch (FetchFailed) {
      return false;
    }
  }

  private function stamp(): string
  {
    return PHP_VERSION . ' ' . Version::APP . "\n";
  }
}
