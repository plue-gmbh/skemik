<?php

declare(strict_types=1);

namespace Admin;

use Support\Clock;

/* The day a timeline files a record under: its date (`Glance::date`) as the
   house lives it, so an evening event stays on its evening and not on the UTC
   day after, and the words for that day in the reader's language. Where the
   collection carries no date it is the day the record was made, the order the
   address gives a list asked for none, so no type is left without a timeline.
   A date known only to its month or year is filed under that and has no day:
   it is held as written, there being no zone to read it in. */
final readonly class Timeline
{
  private const DAY = 'YYYY-MM-DD';

  public function __construct(private ?Column $date, private Clock $clock, private string $language) {}

  /* `Y-m-d`, `Y-m` or `Y`, which compare as they sort; null where the record
     has no date yet, or a new row none at all. */
  public function when(?SheetRow $row): ?string
  {
    $value = $this->date === null ? $row?->entity->createdAt
      : $row?->entity->props->string($this->date->field->key);

    return $value === null || strlen($value) < strlen(self::DAY) ? $value
      : substr($this->clock->render($value), 0, strlen(self::DAY));
  }

  /* The house's day, where the timeline draws its line between what has been and what comes. */
  public function today(): string
  {
    $now = $this->clock->now()->format(Clock::STORED);

    return substr($this->clock->render($now), 0, strlen(self::DAY));
  }

  public function day(string $when): ?\DateTimeImmutable
  {
    return strlen($when) === strlen(self::DAY)
      ? new \DateTimeImmutable($when, new \DateTimeZone('UTC')) : null;
  }

  /* A month's heading, or a year's for a date known only to its year. */
  public function heading(string $when): string
  {
    return strlen($when) === strlen('YYYY') ? $when : $this->says(new \DateTimeImmutable(
      substr($when, 0, strlen('YYYY-MM')) . '-01', new \DateTimeZone('UTC')), 'LLLL y');
  }

  /* An ICU pattern: `d` and `EEE` a day's number and weekday. */
  public function says(\DateTimeImmutable $day, string $pattern): string
  {
    return (string) (new \IntlDateFormatter($this->language, \IntlDateFormatter::NONE,
      \IntlDateFormatter::NONE, 'UTC', null, $pattern))->format($day);
  }
}
