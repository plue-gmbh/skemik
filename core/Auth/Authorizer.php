<?php

declare(strict_types=1);

namespace Auth;

use Domain\Entity;
use Domain\Exceptions\EntityNotFound;
use Storage\Collection;

/* The single chokepoint both paths funnel into; every controller asks it before it
   does anything. A session can never satisfy the machine path and a token never
   the human one: the two methods read disjoint inputs, so no controller can blur
   them. Both scopes are checked here and nowhere else. Write scope refuses. Read
   scope never refuses, because a half-visible graph is a broken one: a type
   outside it is read as the stranger reads it, under `Feed\PublicFilter`, which
   both read halves hand their answer to rather than deciding anything a second
   time. A
   content capability asked without its collection is a programming error and
   throws rather than passing. */
final readonly class Authorizer
{
  public function __construct(
    private Session $session,
    private AccountRepository $accounts,
    private TokenRepository $tokens,
    private GrantRepository $grants,
    private ?string $bearer,
  ) {
    assert(!Role::Viewer->can(Capability::WriteContent) && !Role::Editor->can(Capability::EraseData)
      && !Role::Editor->can(Capability::ManageAccounts) && !Role::Editor->can(Capability::RunUpdate),
      'a role may do what is kept for the owner');
    assert(TypeScope::only(['Event', 'Invoice'])->covers(TypeScope::only(['Event']))
      && !TypeScope::only(['Event'])->covers(TypeScope::all()), 'a narrowed scope widens');
  }

  public function requireHuman(Capability $capability, ?Collection $collection = null): Account
  {
    if ($capability->isAboutContent() && $collection === null) {
      throw new \LogicException(sprintf('%s asked without a collection', $capability->value));
    }
    $account = $this->requireAccount();
    if (!$account->can($capability)) {
      throw NotPermitted::capability($capability);
    }
    $type = basename((string) $collection?->type);
    if ($capability === Capability::WriteContent && !$account->write->allows($type)) {
      throw NotPermitted::outsideWriteScope($type);
    }

    return $account;
  }

  /* What a screen asks before it draws an editor, so it never offers what the
     write would refuse. */
  public function writes(Account $account, Collection $collection): bool
  {
    return $account->can(Capability::WriteContent) && $account->write->allows(basename($collection->type));
  }

  /* Whether this person carries and lets out the collection's fields: what the
     field dialog offers, and what their dump's catalogue shows as a key that
     arranges the collection reads it. */
  public function arranges(Account $account, Collection $collection): bool
  {
    return $account->can(Capability::ManageFields) && $this->writes($account, $collection);
  }

  /* The write half for a machine. A write makes a record or names one, so the
     record decides the verb: none is `create`, one is `set`. Every field written
     is asked, so the refusal names the first one no rule covers, as the document
     spelt it. A record none of the key's rules here binds to is not the key's,
     and is answered as its reads answer one: absence, never a refusal.
     @param array<string, string> $fields each field key the write carries, to its name in the document */
  public function requireMachine(Collection $collection, array $fields, ?Entity $record = null): Token
  {
    $token = $this->requireToken();
    $capability = $record === null ? GrantCapability::Create : GrantCapability::Set;
    $rules = array_filter($this->grants->forToken($token),
      fn (Grant $rule): bool => $rule->bindsTo($record?->id) && $rule->on($collection->slug, $capability));
    if ($record !== null && $rules === []) {
      throw EntityNotFound::withId($record->slug->value);
    }
    foreach ($fields === [] ? [null] : array_keys($fields) as $field) {
      $covered = array_any($rules, fn (Grant $rule): bool =>
        $rule->covers($collection->slug, $field, $capability));
      if (!$covered) {
        throw NotPermitted::grant($collection->slug->value, $field === null ? null : $fields[$field],
          $capability);
      }
    }

    return $token;
  }

  /* Asked before a write looks its record up: a key holding no rule to set
     anything in the collection is refused first, so the lookup cannot tell it
     whether the address it aimed at exists. */
  public function requireRule(Collection $collection, GrantCapability $capability): Token
  {
    $token = $this->requireToken();
    $held = array_any($this->grants->forToken($token),
      fn (Grant $rule): bool => $rule->on($collection->slug, $capability));

    return $held ? $token : throw NotPermitted::grant($collection->slug->value, null, $capability);
  }

  /* The read half for a machine: the `read` rules the bearer holds, which only
     `Feed\PublicFilter`'s gates can apply, since a list names no one record to
     ask a subject about. No key is the anonymous reading; a key
     that is refused is a 401 and not that reading, or a caller whose token
     expired would be served less without being told why.
     @return ?list<Grant> null where no key was sent */
  public function machineReads(): ?array
  {
    if ($this->bearer === null) {
      return null;
    }

    return array_values(array_filter($this->grants->forToken($this->requireToken()),
      fn (Grant $rule): bool => $rule->capability === GrantCapability::Read));
  }

  /* The collections the bearer may arrange, whose catalogue entries then show
     the arrangement rather than a reading (`Feed\Catalog`); none without a key.
     @return list<string> collection slugs */
  public function machineArranges(): array
  {
    if ($this->bearer === null) {
      return [];
    }
    $rules = array_filter($this->grants->forToken($this->requireToken()),
      fn (Grant $rule): bool => $rule->capability === GrantCapability::Arrange);

    return array_values(array_map(fn (Grant $rule): string => $rule->collection->value, $rules));
  }

  /* Asked before a write looks anything up, so a caller without a key learns
     nothing from the answer, not even whether an address exists. */
  public function requireToken(): Token
  {
    $token = $this->bearer === null ? null : $this->tokens->authenticate($this->bearer);

    return $token ?? throw NotAuthenticated::machine();
  }

  /* The read half for a person: outside the read scope the answer is the
     public reading of that type, never a refusal. */
  public function readsAsPublic(Account $account, string $type): bool
  {
    return !$account->read->allows(basename($type));
  }

  public function requireAccount(): Account
  {
    return $this->current() ?? throw NotAuthenticated::human();
  }

  /* A session outlives a deleted account; that reads as nobody, not as an error. */
  public function current(): ?Account
  {
    $accountId = $this->session->accountId();
    try {
      $account = $accountId === null ? null : $this->accounts->find($accountId);
    } catch (EntityNotFound) {
      $account = null;
    }

    return $account !== null && $account->disabledAt === null ? $account : null;
  }
}
