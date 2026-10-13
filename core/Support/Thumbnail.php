<?php

declare(strict_types=1);

namespace Support;

/* The library's preview: one size and no parameter, so the work a stranger can
   provoke is the library, once per image that previews; one that cannot is tried
   again at each view, bounded by the cold previews' rate limit, since a failure
   remembered would keep one that passes, a full disk. Raster images through GD
   and nothing else, an animation's first frame. Keyed by the file and not the
   row, since a replaced asset is a new file with a preview of its own. The pixel
   budget is read before a byte is decoded and shrinks with the memory PHP has
   left, because an allocation that dies is a fatal error no catch reaches.
   It never throws into a request: a preview that cannot be made is a glyph. */
final readonly class Thumbnail
{
  public const LONG_EDGE = 480;
  private const MOST_PIXELS = 40_000_000;
  private const BYTES_A_PIXEL = 5;
  private const DECODERS = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
    'image/gif' => 'imagecreatefromgif', 'image/webp' => 'imagecreatefromwebp',
    'image/avif' => 'imagecreatefromavif'];

  public function __construct(
    private string $directory,
    private Orientation $orientation = new Orientation(),
  ) {}

  public function handles(string $mime): bool
  {
    return function_exists('imagewebp') && function_exists(self::DECODERS[$mime] ?? '');
  }

  public function cached(string $name): ?string
  {
    return is_file($this->target($name)) ? $this->target($name) : null;
  }

  /* A purged asset's preview leaves with it, its lock too. */
  public function discard(string $name): void
  {
    foreach ([$this->target($name), $this->target($name) . '.lock'] as $path) {
      is_file($path) && unlink($path);
    }
  }

  /* Under an exclusive lock, so a hundred requests for a cold tile render it once. */
  public function make(string $name, string $mime, string $source): ?string
  {
    $made = is_dir($this->directory) || mkdir($this->directory, 0700, true) || is_dir($this->directory);
    if (!$this->handles($mime) || !$made) {
      return null;
    }
    $lock = fopen($this->target($name) . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
      return null;
    }
    try {
      return $this->cached($name) ?? $this->render($mime, $source, $this->target($name));
    } catch (\Throwable) {
      return null;
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  private function render(string $mime, string $source, string $target): ?string
  {
    $size = getimagesize($source);
    if ($size === false || $size[0] * $size[1] > $this->budget()) {
      return null;
    }
    $image = (self::DECODERS[$mime])($source);
    if ($image === false || !imagepalettetotruecolor($image)) {
      return null;
    }
    $scale = min(1, self::LONG_EDGE / max($size[0], $size[1]));
    $small = imagescale($image, max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale)));
    $turn = $mime === 'image/jpeg' ? $this->orientation->of($source) : 1;
    $upright = $small === false ? false : $this->upright($small, $turn);
    $written = $upright !== false && imagewebp($upright, $target . '.part', 80)
      && rename($target . '.part', $target);

    return $written ? $target : null;
  }

  /* A mirrored turn is the plain one flipped across. */
  private function upright(\GdImage $image, int $orientation): \GdImage|false
  {
    $angle = [3 => 180, 4 => 180, 5 => 270, 6 => 270, 7 => 90, 8 => 90][$orientation] ?? 0;
    $turned = $angle === 0 ? $image : imagerotate($image, $angle, 0);
    $mirrored = in_array($orientation, [2, 4, 5, 7], true);

    return $turned !== false && $mirrored && !imageflip($turned, IMG_FLIP_HORIZONTAL) ? false : $turned;
  }

  private function budget(): int
  {
    $limit = ini_parse_quantity((string) ini_get('memory_limit'));
    $free = $limit <= 0 ? PHP_INT_MAX : $limit - memory_get_usage();

    return min(self::MOST_PIXELS, intdiv(max(0, $free), self::BYTES_A_PIXEL));
  }

  private function target(string $name): string
  {
    return $this->directory . '/' . basename($name) . '.webp';
  }
}
