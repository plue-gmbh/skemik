<?php

declare(strict_types=1);

namespace Admin;

use Auth\Token;

/* One live key as the Grants card lists it: its rules one line per set of
   collections allowed the same for the row, each rule for its dialog, which
   names the key once above them, and what replaced it where a newer key of
   its name stands, which is then the one re-issued. When it runs out and when
   it was last used are apart, since the card gives each a line of its own. */
final readonly class RegisterEntry
{
  /** @param array<string, string> $rules each rule as it reads after the key's name, by its id
      @param array<string, string> $stale why a rule allows nothing, by its id */
  public function __construct(
    public Token $token,
    public string $line,
    public string $expires,
    public string $used,
    public string $issued,
    public array $rules,
    public array $stale = [],
    public ?string $replaced = null,
  ) {}
}
