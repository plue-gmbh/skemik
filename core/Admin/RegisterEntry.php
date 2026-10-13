<?php

declare(strict_types=1);

namespace Admin;

use Auth\Token;

/* One live key as the Grants card lists it: its rules one line per set of
   collections allowed the same for the row, each rule's sentence for its
   dialog, the question its revoke asks, and what replaced it where a newer key
   of its name stands, which is then the one re-issued. */
final readonly class RegisterEntry
{
  /** @param array<string, string> $rules each rule's sentence, by its id
      @param array<string, string> $stale why a rule allows nothing, by its id */
  public function __construct(
    public Token $token,
    public string $line,
    public string $facts,
    public string $issued,
    public string $revoking,
    public array $rules,
    public array $stale = [],
    public ?string $replaced = null,
  ) {}
}
