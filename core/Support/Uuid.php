<?php

declare(strict_types=1);

namespace Support;

/* UUIDv7 as text, the one shape every id in the holdings takes. The twelve bits
   after the version hold the sub-millisecond fraction (RFC 9562, method 3)
   rather than randomness, so ids made a microsecond apart still sort in the
   order they were made, without a counter kept between calls, and while the
   server's clock runs forward: one set back sorts later ids first. An upload's
   address is such an id, so it says to the millisecond when the file came,
   which harms nothing, and its random tail keeps a draft's address unguessable. */
final readonly class Uuid
{
  public const SHAPE = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
  private const TIME_PREFIX = 18;

  public function __construct(private Clock $clock)
  {
    $first = substr($this->generate(), 0, self::TIME_PREFIX);
    assert(preg_match(self::SHAPE, $this->generate()) === 1, 'not a UUIDv7');
    $second = substr($this->generate(), 0, self::TIME_PREFIX);
    assert(strcmp($first, $second) <= 0, 'ids do not sort by creation');
  }

  public function generate(): string
  {
    $microseconds = (int) $this->clock->now()->format('Uu');
    $fraction = intdiv($microseconds % 1000 * 4096, 1000);
    $tail = random_bytes(8);
    $tail[0] = chr(ord($tail[0]) & 0x3f | 0x80);
    $hex = sprintf('%012x7%03x', intdiv($microseconds, 1000), $fraction) . bin2hex($tail);

    return implode('-', [substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4),
      substr($hex, 16, 4), substr($hex, 20)]);
  }
}
