<?php

declare(strict_types=1);

namespace Update;

/* The archive unpacked through `phar` into `staging/`, checked for every entry
   point and copied into `.staged/` under the domain root, since the swap is
   renames and a rename stays on its filesystem. The flat layout makes the swap
   several renames and not one, so it runs while every address answers *being
   updated*: the running tree goes into `.previous/` and comes back where the
   new code cannot take over the holdings. `config.php` is never touched, the
   archive not containing it. `.htaccess` is replaced between `# BEGIN Skemik`
   and `# END Skemik` with every line outside kept: a hoster has a house add the
   line that picks its PHP version there, as IONOS and Strato do, and losing it
   would answer the site with the boot page naming the old PHP. */
final readonly class Installer
{
  public const PAYLOAD = ['core', 'view', 'language', 'migrations', 'index.php', '.htaccess'];
  private const PAPERS = ['README.md', 'LICENSE', 'SECURITY.md'];
  private const ARRIVED = '.arrived';
  private const STAGED = '.staged';
  private const PREVIOUS = '.previous';
  private const BEGIN = '# BEGIN Skemik';
  private const END = '# END Skemik';

  public function __construct(private string $root, private Backup $backup) {}

  /* Whether PHP may rename the tree's entries: on a host where the files belong
     to the FTP account and PHP runs as another user, it may not. */
  public function writable(): bool
  {
    return is_writable($this->root) && !array_any(self::PAYLOAD,
      fn (string $name): bool => is_dir($this->root . '/' . $name) && !is_writable($this->root . '/' . $name));
  }

  public function stage(string $archive): void
  {
    $unpacked = dirname($archive) . '/unpacked';
    $this->backup->remove($unpacked);
    try {
      (new \PharData($archive))->extractTo($unpacked);
    } catch (\Throwable $failure) {
      throw UpdateRefused::because('the archive could not be unpacked: ' . $failure->getMessage());
    }
    $tree = $this->tree($unpacked);
    foreach (self::PAYLOAD as $name) {
      file_exists($tree . '/' . $name) || throw UpdateRefused::release('the archive holds no ' . $name);
    }
    $this->backup->remove($this->root . '/' . self::STAGED);
    $this->copyTree($tree, $this->root . '/' . self::STAGED);
    $this->backup->remove($unpacked);
  }

  /* The tree as it ran goes aside, the staged one into place; a rename that
     fails puts back what was moved. */
  public function swap(): void
  {
    [$previous, $staged] = [$this->root . '/' . self::PREVIOUS, $this->root . '/' . self::STAGED];
    $this->backup->remove($previous);
    mkdir($previous, 0700);
    $this->htaccess($staged);
    try {
      foreach (self::PAYLOAD as $name) {
        rename($this->root . '/' . $name, $previous . '/' . $name)
          || throw UpdateRefused::because('the swap could not move ' . $name . ' aside');
      }
      foreach (self::PAYLOAD as $name) {
        rename($staged . '/' . $name, $this->root . '/' . $name)
          || throw UpdateRefused::because('the swap could not move ' . $name . ' into place');
      }
      $this->papers($staged, $previous);
    } catch (UpdateRefused $refused) {
      $this->revert();
      throw $refused;
    }
    $this->backup->remove($staged);
    $this->recompiled();
  }

  /* The tree as it ran, back from `.previous/`: for the release that could not take over the holdings. */
  public function revert(): void
  {
    $previous = $this->root . '/' . self::PREVIOUS;
    foreach ([...self::PAYLOAD, ...self::PAPERS] as $name) {
      if (!file_exists($previous . '/' . $name)) {
        continue;
      }
      is_dir($this->root . '/' . $name) ? $this->backup->remove($this->root . '/' . $name)
        : (is_file($this->root . '/' . $name) && unlink($this->root . '/' . $name));
      rename($previous . '/' . $name, $this->root . '/' . $name);
    }
    foreach (self::PAPERS as $name) {
      is_file($previous . '/' . $name . self::ARRIVED) && unlink($this->root . '/' . $name);
    }
    $this->backup->remove($previous);
    $this->recompiled();
  }

  /* What a release says of itself beside the code: every release stamps its own
     licence, so the one in an install has to be the running release's. Each the
     archive holds replaces the last, which is kept aside for the way back, and
     one with none before it is marked, so going back removes it again. */
  private function papers(string $staged, string $previous): void
  {
    foreach (self::PAPERS as $name) {
      if (!is_file($staged . '/' . $name)) {
        continue;
      }
      is_file($this->root . '/' . $name) ? rename($this->root . '/' . $name, $previous . '/' . $name)
        : touch($previous . '/' . $name . self::ARRIVED);
      rename($staged . '/' . $name, $this->root . '/' . $name)
        || throw UpdateRefused::because('the swap could not move ' . $name . ' into place');
    }
  }

  public function discard(): void
  {
    $this->backup->remove($this->root . '/' . self::STAGED);
  }

  public function settle(): void
  {
    $this->backup->remove($this->root . '/' . self::PREVIOUS);
  }

  /* The archive unpacks to the one folder the zip under *Install* holds, or to the payload itself. */
  private function tree(string $unpacked): string
  {
    $entries = array_values(array_diff(scandir($unpacked) ?: [], ['.', '..']));

    return count($entries) === 1 && is_dir($unpacked . '/' . $entries[0]) ? $unpacked . '/' . $entries[0] : $unpacked;
  }

  private function htaccess(string $staged): void
  {
    $live = (string) file_get_contents($this->root . '/.htaccess');
    [$begin, $end] = [strpos($live, self::BEGIN), strpos($live, self::END)];
    if ($begin === false || $end === false || $end < $begin) {
      return;
    }
    $shipped = (string) file_get_contents($staged . '/.htaccess');
    file_put_contents($staged . '/.htaccess', substr($live, 0, $begin) . trim($shipped) . "\n"
      . ltrim(substr($live, $end + strlen(self::END)), "\n"));
  }

  /* Readable by the web server and not only by PHP, as a copied release is. */
  private function copyTree(string $from, string $to): void
  {
    mkdir($to, 0755, true) || throw UpdateRefused::because('could not make ' . $to);
    foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $name) {
      [$source, $target] = [$from . '/' . $name, $to . '/' . $name];
      if (is_dir($source)) {
        $this->copyTree($source, $target);
      } elseif (!copy($source, $target)) {
        throw UpdateRefused::because('could not copy ' . $source);
      }
    }
  }

  /* A host that keeps compiled files without checking their time would run the
     old tree until restarted; one that restricts the call answers a warning,
     which is the host's log's and not the owner's screen's. */
  private function recompiled(): void
  {
    if (!function_exists('opcache_reset')) {
      return;
    }
    set_error_handler(fn (): bool => true);
    try {
      opcache_reset();
    } finally {
      restore_error_handler();
    }
  }
}
