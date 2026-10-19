<?php

declare(strict_types=1);

namespace Admin;

use Auth\Token;
use Auth\TokenRepository;
use Support\Clock;
use Support\Words;

/* The live keys as the issue form names and dates them: a name is a key's
   identity, compared without case, and only a live key counts, since an
   expired or revoked one is not what a second rule is added to. */
final readonly class LiveKeys
{
  public function __construct(
    private TokenRepository $tokens,
    private KeyFacts $facts,
    private Clock $clock,
    private Moments $moments,
    private Words $words,
  ) {}

  /* A UUIDv7 orders by time where `created_at`, to the second, cannot.
     @return array<string, Token> newest first, by id */
  public function live(): array
  {
    $now = $this->clock->now()->format(Clock::STORED);
    $live = [];
    foreach ($this->tokens->all() as $token) {
      if ($token->revokedAt === null && $token->expiresAt > $now) {
        $live[$token->id] = $token;
      }
    }

    return $live;
  }

  /** @return list<string> the names live tokens carry, for the name picker */
  public function labels(): array
  {
    return array_values(array_unique(array_map(fn (Token $token): string => $token->label, $this->live())));
  }

  /* The newest live token under a name, in any case: the one a second rule is
     re-issued from. */
  public function named(string $label): ?Token
  {
    return $this->liveNamed($label)[0] ?? null;
  }

  /** @return list<Token> every live token under a name, newest first: what a re-issue may stop */
  public function liveNamed(string $label): array
  {
    return array_values(array_filter($this->live(),
      fn (Token $token): bool => $this->alike($token->label, $label)));
  }

  public function alike(string $one, string $other): bool
  {
    return mb_strtolower(trim($one)) === mb_strtolower(trim($other));
  }

  /* What issuing under a live name does, said before it is done. */
  public function adding(Token $token): string
  {
    return $this->words->get('grant.adding', ['name' => $token->label,
      'until' => $this->moments->day($token->expiresAt)]);
  }

  /* Why a name's new key is not issued: it would read a collection two ways (`Auth\Grant::readTwoWays`). */
  public function twoWays(string $name, string $collection): string
  {
    return $this->words->get('grant.two_ways', ['name' => $name, 'collection' => $collection]);
  }

  /* A new grant's proposed expiry, a year from today: finite, as every token is. */
  public function until(): string
  {
    return substr($this->clock->render($this->clock->now()->add(new \DateInterval('P1Y'))
      ->format(Clock::STORED)), 0, strlen('YYYY-MM-DD'));
  }
}
