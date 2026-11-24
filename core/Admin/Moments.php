<?php

declare(strict_types=1);

namespace Admin;

use Support\Clock;
use Support\UnreadableTime;
use Support\Words;

/* A stored time worded for the reader, in the chrome's language and the install's
   timezone: the day alone, or the day with its time as `IntlDateFormatter`
   styles it, short as a rule. A date passes as the day it names, there being no
   zone to read it in, and one known only to its month or year is said as
   written, since no style words it. How long ago stands where the question is
   whether something still runs: a key's last use, the last backup. */
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

  public function ago(string|\DateTimeInterface $at): string
  {
    $then = is_string($at) ? new \DateTimeImmutable($at) : $at;
    $minutes = intdiv($this->clock->now()->getTimestamp() - $then->getTimestamp(), 60);

    return match (true) {
      $minutes < 60 => $this->words->get('ago.hour'),
      $minutes < 1440 => $this->words->count('ago', 'hours', intdiv($minutes, 60)),
      default => $this->words->count('ago', 'days', intdiv($minutes, 1440)),
    };
  }

  /* The install's day first, today, yesterday or its date, since seven hours ago may be last night.
     Yesterday a day ago says it twice, so there the day stands alone. */
  public function recent(\DateTimeInterface $at): string
  {
    $day = \DateTimeImmutable::createFromInterface($at)->setTimezone($this->clock->zone)->format('Y-m-d');
    $today = $this->clock->today();
    $yesterday = new \DateTimeImmutable($today)->modify('-1 day')->format('Y-m-d');
    $named = match ($day) {
      $today => $this->words->get('ago.today'),
      $yesterday => $this->words->get('ago.yesterday'),
      default => $this->day($at),
    };
    $twice = $day === $yesterday && $this->clock->now()->getTimestamp() - $at->getTimestamp() >= 86400;

    return $twice ? $named : $named . ', ' . $this->ago($at);
  }

  /* The install's wall time read back as a wall time, so no zone moves it twice. */
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
