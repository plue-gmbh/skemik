<?php

declare(strict_types=1);

namespace Support;

/* A zip archive written entry by entry, stored and never compressed: a dump is
   photographs and video, which deflate would only warm the server for, and the
   format's few records are less than an extension *Requirements* would have to
   name. Opened on the first entry, so a box nobody writes to is no file, and a
   file is streamed once, its checksum taken as it passes and patched into the
   header behind it. The ZIP64 fields are written only where a count or a size
   overflows the original ones, so a small box opens in every reader there is.
   Not readonly: a writer holds its handle and the entries its directory lists. */
final class Zip
{
  private const LIMIT = 0xFFFFFFFF;
  private const CHUNK = 1 << 20;
  /** @var ?resource */
  private $handle = null;
  /** @var list<array{string, int, int, int}> name, size, checksum, offset */
  private array $entries = [];

  public function __construct(public readonly string $path, private readonly \DateTimeImmutable $at) {}

  public function add(string $name, string $body): void
  {
    $this->entry($name, strlen($body), function () use ($body): int {
      $this->write($body);

      return (int) hexdec(hash('crc32b', $body));
    });
  }

  public function addFile(string $name, string $file): void
  {
    $this->entry($name, (int) filesize($file), function () use ($file): int {
      $in = fopen($file, 'rb') ?: throw ZipFailed::reading($file);
      $sum = hash_init('crc32b');
      while (!feof($in)) {
        $chunk = fread($in, self::CHUNK);
        $chunk === false && throw ZipFailed::reading($file);
        hash_update($sum, $chunk);
        $this->write($chunk);
      }
      fclose($in);

      return (int) hexdec(hash_final($sum));
    });
  }

  public function close(): void
  {
    if ($this->handle === null) {
      return;
    }
    $start = $this->offset();
    foreach ($this->entries as [$name, $size, $sum, $offset]) {
      $this->write($this->listed($name, $size, $sum, $offset));
    }
    $this->write($this->end($start, $this->offset() - $start));
    fclose($this->handle);
    $this->handle = null;
  }

  /* What a failed run leaves: nothing. */
  public function discard(): void
  {
    $this->handle === null || fclose($this->handle);
    $this->handle = null;
    is_file($this->path) && unlink($this->path);
  }

  /** @param \Closure(): int $body writes the bytes and answers their checksum */
  private function entry(string $name, int $size, \Closure $body): void
  {
    $offset = $this->offset();
    $wide = $size >= self::LIMIT;
    $extra = $wide ? pack('vvPP', 1, 16, $size, $size) : '';
    $this->write(pack('VvvvvvVVVvv', 0x04034b50, $wide ? 45 : 20, 0x0800, 0, $this->time(), $this->date(), 0,
      min($size, self::LIMIT), min($size, self::LIMIT), strlen($name), strlen($extra)) . $name . $extra);
    $sum = $body();
    fseek($this->handle(), $offset + 14);
    $this->write(pack('V', $sum));
    fseek($this->handle(), 0, SEEK_END);
    $this->entries[] = [$name, $size, $sum, $offset];
  }

  /* One record of the central directory, the ZIP64 extra naming only the fields it stands in for. */
  private function listed(string $name, int $size, int $sum, int $offset): string
  {
    $wide = array_values(array_filter([$size, $size, $offset], fn (int $value): bool => $value >= self::LIMIT));
    $extra = $wide === [] ? '' : pack('vv', 1, 8 * count($wide)) . pack('P*', ...$wide);

    return pack('VvvvvvvVVVvvvvvVV', 0x02014b50, $wide === [] ? 20 : 45, $wide === [] ? 20 : 45, 0x0800, 0,
      $this->time(), $this->date(), $sum, min($size, self::LIMIT), min($size, self::LIMIT), strlen($name),
      strlen($extra), 0, 0, 0, 0, min($offset, self::LIMIT)) . $name . $extra;
  }

  private function end(int $start, int $size): string
  {
    $count = count($this->entries);
    $plain = pack('VvvvvVVv', 0x06054b50, 0, 0, min($count, 0xFFFF), min($count, 0xFFFF),
      min($size, self::LIMIT), min($start, self::LIMIT), 0);
    if ($count < 0xFFFF && $size < self::LIMIT && $start < self::LIMIT) {
      return $plain;
    }

    return pack('VPvvVVPPPP', 0x06064b50, 44, 45, 45, 0, 0, $count, $count, $size, $start)
      . pack('VVPV', 0x07064b50, 0, $start + $size, 1) . $plain;
  }

  private function time(): int
  {
    return ((int) $this->at->format('G') << 11) | ((int) $this->at->format('i') << 5)
      | ((int) $this->at->format('s') >> 1);
  }

  private function date(): int
  {
    return (((int) $this->at->format('Y') - 1980) << 9) | ((int) $this->at->format('n') << 5)
      | (int) $this->at->format('j');
  }

  private function write(string $bytes): void
  {
    if (fwrite($this->handle(), $bytes) !== strlen($bytes)) {
      throw ZipFailed::writing($this->path);
    }
  }

  private function offset(): int
  {
    $at = ftell($this->handle());

    return $at === false ? throw ZipFailed::writing($this->path) : $at;
  }

  /** @return resource */
  private function handle()
  {
    if ($this->handle === null) {
      $folder = dirname($this->path);
      $opened = (is_dir($folder) || mkdir($folder, 0700, true)) ? fopen($this->path, 'xb') : false;
      $this->handle = $opened ?: throw ZipFailed::writing($this->path);
    }

    return $this->handle;
  }
}
