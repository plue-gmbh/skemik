<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRepository;
use Auth\Role;
use Auth\TypeScope;
use Feed\PublicFilter;
use Profile\Profile;
use Profile\TypeDefinition;
use Storage\EntityRepository;
use Support\Words;

/* What the Accounts card reads: a row per account, owners first since a house
   asks first who can still hand out the keys, and the thirty types its scope
   editors tick. A scope is said by the types it names and never by the ones it
   leaves out, and it says where the rest is read, because a narrowed read scope
   hides nothing: a type left out is read as the public reads it. Which types have
   no public reading is `Feed\PublicFilter`'s answer, so the dialog can say that
   leaving one out leaves nothing to read, not only the drafts. The picture is
   the account menu's, drawn as a chip draws it, so one the reader may not see
   falls back to the initials. */
final readonly class Colleagues
{
  public function __construct(
    private AccountRepository $accounts,
    private EntityRepository $entities,
    private Profile $profile,
    private PublicFilter $filter,
    private Rows $rows,
    private Moments $moments,
    private Words $words,
  ) {}

  /** @return list<Colleague> by role, then by name */
  public function all(Account $reader): array
  {
    $accounts = $this->accounts->all();
    $owners = count(array_filter($accounts, fn (Account $each): bool =>
      $each->role === Role::Owner && $each->disabledAt === null));
    $colleagues = array_map(fn (Account $each): Colleague => $this->colleague($each, $reader, $owners), $accounts);
    $collator = new \Collator($this->words->language);
    $rank = fn (Colleague $row): int => (int) array_search($row->account->role, Role::cases(), true);
    usort($colleagues, fn (Colleague $one, Colleague $other): int => $rank($one) <=> $rank($other)
      ?: (int) $collator->compare($one->name, $other->name));

    return $colleagues;
  }

  /** @return array<string, string> every type's name to its word, as the chrome's language sorts them */
  public function types(): array
  {
    $types = [];
    foreach ($this->profile->types() as $type) {
      $types[$type->name] = $this->words->get('type.' . $type->name);
    }
    (new \Collator($this->words->language))->asort($types);

    return $types;
  }

  /** @return list<string> the types a stranger never reads, so none outside a read scope either */
  public function unreadable(): array
  {
    return array_values(array_map(fn (TypeDefinition $type): string => $type->name,
      array_filter($this->profile->types(), fn (TypeDefinition $type): bool =>
        !$this->filter->passesType($type->name))));
  }

  private function colleague(Account $account, Account $reader, int $owners): Colleague
  {
    $person = $this->entities->find($account->person);
    $name = (string) $person->props->string('name');
    $picture = array_find($this->rows->chips($reader, $person, [])['image'] ?? [],
      fn (Chip $chip): bool => $chip->preview !== null)?->preview;
    $login = $account->lastLoginAt === null ? $this->words->get('account.never')
      : $this->words->get('account.last_login', ['date' => $this->moments->day($account->lastLoginAt)]);

    $last = $owners === 1 && $account->role === Role::Owner && $account->disabledAt === null;

    return new Colleague($account, $name, $this->sentence($account, $account->id === $reader->id),
      $account->email . ' · ' . $login, $picture, $last);
  }

  /* The reader's own row says so in its sentence, in the second person, where a
     flag beside the name would cost the name its width. */
  private function sentence(Account $account, bool $yours): string
  {
    $person = $yours ? 'yours_' : '';
    $does = match ($account->role) {
      Role::Owner => [$this->words->get('account.' . $person . 'does_all')],
      Role::Editor => $this->editor($person, $account),
      Role::Viewer => [$this->scope($person . 'reads', $account->read)],
    };

    return $this->words->get($yours ? 'account.yours' : 'account.sentence',
      ['role' => $this->words->get('account.role.' . $account->role->value), 'does' => implode(', ', $does)]);
  }

  /* Two whole scopes are one clause, since "writes every type, reads every type" reads as a list of two.
     @return list<string> */
  private function editor(string $person, Account $account): array
  {
    if ($account->write->types === null && $account->read->types === null) {
      return [$this->words->get('account.' . $person . 'writes_reads_all')];
    }

    return [$this->scope($person . 'writes', $account->write), $this->scope($person . 'reads', $account->read)];
  }

  private function scope(string $verb, TypeScope $scope): string
  {
    if ($scope->types === null) {
      return $this->words->get('account.' . $verb . '_all');
    }
    $named = array_intersect_key($this->types(), array_flip($scope->types));

    return $this->words->get('account.' . $verb, ['types' => implode(', ', $named)]);
  }
}
