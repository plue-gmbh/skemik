<?php

declare(strict_types=1);

namespace Auth;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\ValueTaken;
use Domain\Violation;
use Storage\Transaction;
use Support\Clock;
use Support\Uuid;

/* `account` is the only table that points into `entity`, and the direction is
   deliberate: the sidecar knows its `Person`, the person knows nothing about the
   account, so a content row carries no trace of whether somebody can log in as it.
   The last owner is guarded here and not in a controller, inside the write's own
   `begin immediate`, so two owners demoting each other at once cannot both
   succeed. Disabling counts as demoting: a house whose only owner is disabled has
   no owner. `heldBy` is the question `Storage\Trash` asks before a
   `Person` is trashed or purged, so the refusal names the account rather
   than surfacing as a foreign-key error. */
final readonly class AccountRepository
{
  public function __construct(
    private \PDO $db,
    private Uuid $uuid,
    private Clock $clock,
    private Transaction $transaction = new Transaction(),
  ) {}

  public function find(string $id): Account
  {
    return $this->one('id = :value', $id) ?? throw EntityNotFound::withId($id);
  }

  public function byEmail(string $email): ?Account
  {
    return $this->one('email = :value', Account::address($email));
  }

  public function heldBy(EntityId $person): ?Account
  {
    return $this->one('person_id = :value', $person->value);
  }

  /** @return list<Account> */
  public function all(): array
  {
    $statement = $this->db->prepare('select * from account order by email');
    $statement->execute();

    return array_map($this->account(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function create(Entity $person, string $email, Role $role, TypeScope $write,
    TypeScope $read): Account
  {
    if ($person->typeName() !== 'Person') {
      throw MalformedValue::named('person holding an account', $person->type);
    }
    $account = new Account($this->uuid->generate(), $person->id, $email, $role,
      $write, $read, $this->now());
    $this->transaction->run($this->db, function () use ($account): void {
      $this->requireFree($account);
      $this->db->prepare('insert into account (id, person_id, email, role, write_scope,'
        . ' read_scope, created_at) values (:id, :person, :email, :role,'
        . ' :write, :read, :created)')
        ->execute([...$this->columns($account), 'person' => $account->person->value,
          'created' => $account->createdAt]);
    });

    return $account;
  }

  /* Everything but the person, which an account never moves to, and the stamps. */
  public function save(Account $account): void
  {
    $this->transaction->run($this->db, function () use ($account): void {
      $this->requireFree($account);
      if (!$account->can(Capability::ManageAccounts)) {
        $this->requireAnotherOwner($account);
      }
      $this->db->prepare('update account set email = :email, role = :role, write_scope = :write,'
        . ' read_scope = :read, disabled_at = :disabled'
        . ' where id = :id')
        ->execute([...$this->columns($account), 'disabled' => $account->disabledAt]);
    });
  }

  public function delete(Account $account): void
  {
    $this->transaction->run($this->db, function () use ($account): void {
      $this->requireAnotherOwner($account);
      $this->db->prepare('delete from account where id = :id')
        ->execute(['id' => $account->id]);
    });
  }

  public function recordLogin(Account $account): void
  {
    $this->db->prepare('update account set last_login_at = :now where id = :id')
      ->execute(['now' => $this->now(), 'id' => $account->id]);
  }

  private function requireAnotherOwner(Account $account): void
  {
    $statement = $this->db->prepare("select count(*) from account"
      . " where role = 'owner' and disabled_at is null and id <> :id");
    $statement->execute(['id' => $account->id]);
    if ((int) $statement->fetchColumn() === 0) {
      throw AccountRefused::lastOwner();
    }
  }

  private function requireFree(Account $account): void
  {
    $holder = $this->byEmail($account->email);
    if ($holder !== null && $holder->id !== $account->id) {
      throw new ValueTaken(Violation::stated('email', 'taken'));
    }
  }

  private function one(string $where, string $value): ?Account
  {
    $statement = $this->db->prepare('select * from account where ' . $where);
    $statement->execute(['value' => $value]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $this->account($row);
  }

  /** @return array<string, string|null> */
  private function columns(Account $account): array
  {
    return [
      'id' => $account->id,
      'email' => $account->email,
      'role' => $account->role->value,
      'write' => $account->write->stored(),
      'read' => $account->read->stored(),
    ];
  }

  /** @param array<string, string|null> $row */
  private function account(array $row): Account
  {
    return new Account(
      (string) $row['id'],
      new EntityId((string) $row['person_id']),
      (string) $row['email'],
      Role::from((string) $row['role']),
      TypeScope::parse($row['write_scope']),
      TypeScope::parse($row['read_scope']),
      (string) $row['created_at'],
      $row['last_login_at'],
      $row['disabled_at'],
    );
  }

  private function now(): string
  {
    return $this->clock->now()->format(Clock::STORED);
  }
}
