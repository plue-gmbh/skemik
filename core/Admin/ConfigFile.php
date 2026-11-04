<?php

declare(strict_types=1);

namespace Admin;

/* `config.php`, the one PHP the application writes: through `var_export()` and
   never by concatenation, as a draft readable by PHP alone and renamed over the
   file, so no half-written one is ever read, and its compiled copy dropped after,
   since a host whose opcode cache does not look at the file again would go on
   reading the old one. A change writes the file whole from what it read, so a key
   added by hand stays and a comment written there is lost. */
final readonly class ConfigFile
{
  public function __construct(private string $root) {}

  public function path(): string
  {
    return $this->root . '/config.php';
  }

  /* Renaming the draft over the file needs the folder, not the file. */
  public function writable(): bool
  {
    return is_writable($this->root);
  }

  /** What the file returns, each value one `var_export()` writes back as it came.
      @return array<string, mixed> */
  public function read(): array
  {
    $this->forget();
    $read = require $this->path();

    return is_array($read) ? $read : [];
  }

  /** @param array<string, mixed> $changes */
  public function change(array $changes): void
  {
    $this->write([...$this->read(), ...$changes]);
  }

  /** @param array<string, mixed> $config */
  public function write(array $config): void
  {
    $draft = $this->root . '/config-' . bin2hex(random_bytes(8)) . '.php';
    $made = touch($draft) && chmod($draft, 0600)
      && file_put_contents($draft, "<?php\n\nreturn " . var_export($config, true) . ";\n") !== false;
    if (!$made || !rename($draft, $this->path())) {
      is_file($draft) && unlink($draft);
      throw new ConfigUnwritten('config.php could not be written into ' . $this->root);
    }
    $this->forget();
  }

  private function forget(): void
  {
    function_exists('opcache_invalidate') && opcache_invalidate($this->path(), true);
  }
}
