<?php

declare(strict_types=1);

namespace Support;

/* What stops every address before a request is served: the sentence naming it,
   and whether it passes by itself, an update or a restore under way, which the
   page then waits out instead of calling it a failure. Reached from PHP 8.2 up,
   as the boot refusal is. */
final readonly class Halt
{
  public function __construct(public string $sentence, public bool $passes = false) {}
}
