<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\GrantRepository;
use Auth\Token;
use Storage\Collection;
use Storage\CollectionStore;

/* The Grants card's rows: every live key, newest first, worded by the
   badge's `Grants`, so a rule reads the same on the home screen as on its
   collection. Only the newest key of a name is re-issued, since it holds every
   rule of the name, and a re-issue of an older one would leave the name's
   newest key the narrower. Beneath them the set-up key is issued: one key
   arranging every collection, since a script setting a fresh install up needs
   every one and thirty badges are the clicking it replaces. */
final readonly class Register
{
  public function __construct(
    private Grants $reviews,
    private LiveKeys $keys,
    private GrantRepository $grants,
    private CollectionStore $collections,
    private KeyFacts $facts,
  ) {}

  /** @return list<string> every collection's slug, what the set-up key arranges */
  public function collections(): array
  {
    return array_map(fn (Collection $collection): string => $collection->slug->value, $this->collections->all());
  }

  /** @return list<RegisterEntry> */
  public function entries(Account $account): array
  {
    $newest = [];
    $entries = [];
    foreach ($this->keys->live() as $token) {
      $name = mb_strtolower(trim($token->label));
      $entries[] = $this->entry($account, $token, $newest[$name] ?? null);
      $newest[$name] ??= $token;
    }

    return $entries;
  }

  /* A re-issue's proposed expiry is a new grant's. */
  public function until(): string
  {
    return $this->keys->until();
  }

  private function entry(Account $account, Token $token, ?Token $newer): RegisterEntry
  {
    $rules = $this->grants->forToken($token);
    $sentences = [];
    $stale = [];
    foreach ($rules as $rule) {
      $sentences[$rule->id] = $this->reviews->sentence($rule, $token->label, $account);
      $stale[$rule->id] = $this->reviews->stale($rule);
    }

    return new RegisterEntry($token, implode('; ', $this->reviews->lines($rules, $account)),
      $this->facts->expiry($token) . ' · ' . $this->facts->used($token), $this->facts->issued($token),
      $sentences, array_filter($stale), $newer === null ? null : $this->facts->replaced($token, $newer));
  }
}
