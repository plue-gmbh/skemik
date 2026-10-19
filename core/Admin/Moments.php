<?php

declare(strict_types=1);

namespace Admin;

use Support\Clock;
use Support\UnreadableTime;
use Support\Words;

/* A stored time worded for the reader, in the chrome's language and the house's
   timezone: the day alone, or the day with its time as `IntlDateFormatter`
   styles it, short as a rule. A date passes as the day it names, there being no
   zone to read it in, and one known only to its month or year is said as
   written, since no style words it. */
final readonly class Moments
{
  private const DAY = 'YYYY-MM-DD';
  private const LOCAL = 'Y-m-d\TH:i:s';

  public function __construct(private Clock $clock, private Words $words) {}

  public function day(string|\DateTimeInterface $at): string
  {
    return $this->worded($at, \IntlDateFormatter::NONE);
  }

  public function time(string|\DateTimeInterface $at, int $style = \IntlDateFormatter::SHORT): string
  {
    return $this->worded($at, $style);
  }

  /* The house's wall time read back as a wall time, so no zone moves it twice. */
  private function worded(string|\DateTimeInterface $at, int $style): string
  {
    if (is_string($at) && strlen($at) < strlen(self::DAY)) {
      return $at;
    }
    $local = is_string($at) ? $this->clock->render($at)
      : \DateTimeImmutable::createFromInterface($at)->setTimezone($this->clock->zone)->format(self::LOCAL);
    $format = '!' . (strlen($local) === strlen(self::DAY) ? 'Y-m-d' : self::LOCAL);
    $moment = \DateTimeImmutable::createFromFormat($format, $local, new \DateTimeZone('UTC'))
      ?: throw UnreadableTime::of($local);

    return (string) (new \IntlDateFormatter($this->words->language, \IntlDateFormatter::MEDIUM, $style, 'UTC'))
      ->format($moment);
  }
}
