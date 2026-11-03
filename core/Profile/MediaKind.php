<?php

declare(strict_types=1);

namespace Profile;

use Profile\Seed\Media;

/* What a house asks of its library, its images or its videos and never its PNGs:
   every format of `Seed\Media::FORMATS` is of one kind, four by its type's first
   half, an archive by `Seed\Media::ARCHIVES` and any other a document. A kind is
   asked as the values `encodingFormat` takes, which a text filter matches from
   their start, so the address finds what the library's screen offers. */
enum MediaKind: string
{
  case Image = 'image';
  case Video = 'video';
  case Audio = 'audio';
  case Document = 'document';
  case Archive = 'archive';
  case Font = 'font';

  private const HALVES = ['image', 'video', 'audio', 'font'];

  public static function of(string $format): self
  {
    $half = (string) strtok($format, '/');

    return match (true) {
      in_array($format, Media::ARCHIVES, true) => self::Archive,
      in_array($half, self::HALVES, true) => self::from($half),
      default => self::Document,
    };
  }

  /** The values a filter on the kind takes: a type's first half, or each format of it.
      @return list<string> */
  public function formats(): array
  {
    return match ($this) {
      self::Archive => Media::ARCHIVES,
      self::Document => array_values(array_filter(array_keys(Media::FORMATS),
        fn (string $format): bool => self::of($format) === self::Document)),
      default => [$this->value . '/'],
    };
  }

  /** The kinds whose filter finds one of these formats, in the order the cases stand.
      @param list<string> $formats
      @return list<self> */
  public static function finding(array $formats): array
  {
    $finds = fn (self $kind): bool => array_any($formats, fn (string $format): bool
      => array_any($kind->formats(), fn (string $value): bool => str_starts_with($format, $value)));

    return array_values(array_filter(self::cases(), $finds));
  }
}
