<?php

declare(strict_types=1);

namespace Support;

/* Reading and writing JSON, the only place json_decode's flags are set, with a
   failure that is a named class, `JsonFailed`, because "throws" with nothing to
   catch is how a decode failure becomes a fatal error on somebody's holdings.
   A number leaves as JSON writes it, 19.90 as `19.9`: formatting a price is the
   page's, which knows the currency and the language. A name given twice is read
   as its last, as every parser a channel uses reads it, and `{}` reads as `[]`,
   so an empty object is refused where an object is asked, an empty `PATCH`
   having no purpose. Reading a file is here so `Profile\Vocabulary` needs no
   reader of its own, and writing one so no report is ever half written. */
final readonly class Json
{
  /** @return array<string, mixed> */
  public function readFile(string $path): array
  {
    $body = @file_get_contents($path);
    if ($body === false) {
      throw JsonFailed::unreadable($path);
    }

    return $this->decode($body);
  }

  /* Written beside the file and moved into place, so a write a full quota cuts short, or two at once, leave the
     last whole file and never half of one: a report is read while every answer is built. */
  public function writeFile(string $path, mixed $value): void
  {
    $part = $path . '.' . bin2hex(random_bytes(4)) . '.part';
    if (file_put_contents($part, $this->encode($value) . "\n") === false || !rename($part, $path)) {
      is_file($part) && unlink($part);
      throw JsonFailed::unwritten($path);
    }
  }

  /** @return array<string, mixed> */
  public function decode(string $body): array
  {
    try {
      $value = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
      throw JsonFailed::malformed($e->getMessage());
    }

    return is_array($value) ? $value : throw JsonFailed::malformed('not an object');
  }

  public function encode(mixed $value): string
  {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }
}
