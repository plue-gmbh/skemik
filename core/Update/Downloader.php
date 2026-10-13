<?php

declare(strict_types=1);

namespace Update;

use Support\Fetch;

/* The archive and its signature fetched into `staging/` in the data folder,
   emptied first so a half fetch of an earlier run is never taken for whole, and
   never unpacked here. The size limit is the download's; what is past it is cut
   off and refused, as an archive shorter than its server said (`Fetch`). */
final readonly class Downloader
{
  public const LIMIT_BYTES = 10_000_000;
  private const ARCHIVE = 'release.zip';

  public function __construct(private string $staging, private Fetch $fetch) {}

  /** @return array{string, string} the archive and its signature file */
  public function fetch(Manifest $manifest): array
  {
    $this->clear();
    is_dir($this->staging) || mkdir($this->staging, 0700, true);
    [$archive, $signature] = [$this->staging . '/' . self::ARCHIVE, $this->staging . '/' . self::ARCHIVE . '.minisig'];
    $this->fetch->save($manifest->archive, $archive, self::LIMIT_BYTES);
    $this->fetch->save($manifest->signature, $signature, 4096);

    return [$archive, $signature];
  }

  public function clear(): void
  {
    foreach (glob($this->staging . '/' . self::ARCHIVE . '*') ?: [] as $file) {
      unlink($file);
    }
  }
}
