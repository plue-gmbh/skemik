<?php

declare(strict_types=1);

namespace Support;

/* The turn a camera writes into a JPEG's EXIF rather than into its pixels, which a
   browser honours and GD ignores. Read from the file's own first bytes, so no
   `exif` extension is asked of the host. 1 is upright, 3 upside down, 6 and 8 a
   quarter turn, and 2, 4, 5 and 7 the same mirrored; anything unreadable is 1. */
final readonly class Orientation
{
  private const HEAD_BYTES = 65536;
  private const TAG = 0x0112;

  public function of(string $path): int
  {
    $tiff = $this->exif((string) file_get_contents($path, false, null, 0, self::HEAD_BYTES));

    return $tiff === null ? 1 : $this->read($tiff);
  }

  /* The segments before the image data, walked by their lengths to the one
     holding EXIF. */
  private function exif(string $jpeg): ?string
  {
    for ($at = 2; str_starts_with($jpeg, "\xFF\xD8") && $at + 10 <= strlen($jpeg); $at += 2 + $length) {
      $length = (int) unpack('n', $jpeg, $at + 2)[1];
      if ($jpeg[$at] !== "\xFF" || $jpeg[$at + 1] === "\xDA" || $length < 2) {
        return null;
      }
      if ($jpeg[$at + 1] === "\xE1" && substr($jpeg, $at + 4, 6) === "Exif\0\0") {
        return substr($jpeg, $at + 10, $length - 8);
      }
    }

    return null;
  }

  private function read(string $tiff): int
  {
    [$short, $long] = match (substr($tiff, 0, 4)) {
      "II*\0" => ['v', 'V'],
      "MM\0*" => ['n', 'N'],
      default => [null, null],
    };
    $directory = $long === null || strlen($tiff) < 8 ? PHP_INT_MAX : (int) unpack($long, $tiff, 4)[1];
    $entries = $directory + 2 <= strlen($tiff) ? (int) unpack((string) $short, $tiff, $directory)[1] : 0;
    for ($entry = $directory + 2; $entries-- > 0 && $entry + 12 <= strlen($tiff); $entry += 12) {
      if ((int) unpack((string) $short, $tiff, $entry)[1] === self::TAG) {
        $turn = (int) unpack((string) $short, $tiff, $entry + 8)[1];

        return $turn >= 1 && $turn <= 8 ? $turn : 1;
      }
    }

    return 1;
  }
}
