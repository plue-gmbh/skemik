<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;

/* One house locale of a work on its record screen: the member written in it, or
   none yet and the records in that locale that could be linked in its place.
   Its age is how much older or newer the member was last saved than the record
   viewed, empty within the hour. */
final readonly class Translation
{
  /** @param list<Translation> $candidates */
  public function __construct(
    public string $locale,
    public ?Entity $record = null,
    public string $label = '',
    public string $state = '',
    public ?string $href = null,
    public array $candidates = [],
    public string $age = '',
  ) {}
}
