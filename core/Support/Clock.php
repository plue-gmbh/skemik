<?php

declare(strict_types=1);

namespace Support;

/* The only source of time, and UTC everywhere except at a person: the house's
   timezone binds in `render()` and `parse()`, where a person reads a time or
   names a period, a day asked of the feed or the day an offer lapses, and never
   in what is stored, sorted or addressed. An event happens at an instant, so a
   house that changes its timezone sees every time move by the difference. A
   `date` passes both without a zone, because a birthday is not an instant and
   must not become the previous evening. Every read is checked by writing it
   back, which refuses 2026-02-30, and a wall time the zone skips is refused
   saying so rather than shifted; the hour that repeats in October resolves to
   its second pass. */
final readonly class Clock
{
  public const STORED = 'Y-m-d\TH:i:s\Z';
  private const DATE = 'Y-m-d';
  private const LOCAL = 'Y-m-d\TH:i:s';
  private const LOCAL_MINUTES = 'Y-m-d\TH:i';

  private \DateTimeZone $utc;

  public function __construct(public \DateTimeZone $zone)
  {
    $this->utc = new \DateTimeZone('UTC');
    assert($this->parse($this->render('2026-07-01T12:00:00Z')) === '2026-07-01T12:00:00Z',
      'render and parse are not inverse');
    assert($this->render('2026-02-28') === '2026-02-28', 'a date gained a zone');
  }

  public function now(): \DateTimeImmutable
  {
    return new \DateTimeImmutable('now', $this->utc);
  }

  public function render(string $stored): string
  {
    if (strlen($stored) === strlen('YYYY-MM-DD')) {
      return $this->read(self::DATE, $stored, $this->utc)->format(self::DATE);
    }

    $instant = $this->read(self::STORED, $stored, $this->utc);

    return $instant->setTimezone($this->zone)->format(self::LOCAL);
  }

  public function parse(string $typed): string
  {
    if (strlen($typed) === strlen('YYYY-MM-DD')) {
      return $this->read(self::DATE, $typed, $this->utc)->format(self::DATE);
    }

    $format = strlen($typed) === strlen('YYYY-MM-DDTHH:MM') ? self::LOCAL_MINUTES : self::LOCAL;

    return $this->read($format, $typed, $this->zone)->setTimezone($this->utc)->format(self::STORED);
  }

  /* A day's first instant as the house lives it, which is one o'clock where the
     clocks skip midnight rather than a day refused. */
  public function dayStart(string $date): string
  {
    return $this->read(self::DATE, $date, $this->zone)->setTimezone($this->utc)->format(self::STORED);
  }

  private function read(string $format, string $value, \DateTimeZone $zone): \DateTimeImmutable
  {
    $moment = \DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
    if ($moment !== false && $moment->format($format) === $value) {
      return $moment;
    }
    $wall = \DateTimeImmutable::createFromFormat('!' . $format, $value, $this->utc);

    throw $wall !== false && $wall->format($format) === $value ? UnreadableTime::skipped($value, $zone->getName())
      : UnreadableTime::of($value);
  }
}
