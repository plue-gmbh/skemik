<?php

declare(strict_types=1);

namespace Auth;

use Domain\Exceptions\EntityNotFound;
use Support\Clock;

/* Holds tokens as the SHA-256 of their secret. Revocation and expiry are checked
   in the same statement that finds the token, with no cache and no grace period,
   and both answer the same `null`: a machine learning why it was refused learns
   something about the house. Its last use is written once an hour at most, or a
   website reading with a key would write the database for every page it shows.
   Issuing is `GrantRepository`'s, because a token is written only together with
   the rules that give it a reason to exist.
   The lookup compares hashes in SQL rather than with `hash_equals`: what a timing
   difference could leak is the hash of a guess the caller chose, not the secret. */
final readonly class TokenRepository
{
  public function __construct(private \PDO $db, private Clock $clock) {}

  public function authenticate(string $secret): ?Token
  {
    $now = $this->clock->now();
    $statement = $this->db->prepare('select * from token where token_hash = :hash'
      . ' and revoked_at is null and expires_at > :now');
    $statement->execute(['now' => $now->format(Clock::STORED),
      'hash' => hash('sha256', $secret)]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);
    if ($row === false) {
      return null;
    }
    if ($row['last_used_at'] === null || $row['last_used_at'] <= $now->modify('-1 hour')->format(Clock::STORED)) {
      $this->db->prepare('update token set last_used_at = :now where id = :id')
        ->execute(['now' => $now->format(Clock::STORED), 'id' => $row['id']]);
    }

    return $this->token($row);
  }

  public function find(string $id): Token
  {
    $statement = $this->db->prepare('select * from token where id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? throw EntityNotFound::withId($id) : $this->token($row);
  }

  /** @return list<Token> newest first, revoked and expired included: this is the register */
  public function all(): array
  {
    $statement = $this->db->prepare('select * from token order by id desc');
    $statement->execute();

    return array_map($this->token(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  /* The plaintext, returned once; `random_bytes(32)` is the whole strength of it. */
  public function insert(Token $token): string
  {
    $secret = bin2hex(random_bytes(32));
    $this->db->prepare('insert into token (id, label, token_hash, created_at, created_by,'
      . ' expires_at) values (:id, :label, :hash, :created_at, :created_by, :expires_at)')
      ->execute(['id' => $token->id, 'label' => trim($token->label),
        'hash' => hash('sha256', $secret), 'created_at' => $token->createdAt,
        'created_by' => $token->createdBy, 'expires_at' => $token->expiresAt]);

    return $secret;
  }

  public function revoke(Token $token): void
  {
    $this->db->prepare('update token set revoked_at = :now where id = :id'
      . ' and revoked_at is null')
      ->execute(['now' => $this->now(), 'id' => $token->id]);
  }

  /** @param array<string, string|null> $row */
  private function token(array $row): Token
  {
    return new Token((string) $row['id'],
      (string) $row['label'], (string) $row['created_at'], (string) $row['created_by'],
      (string) $row['expires_at'], $row['last_used_at'], $row['revoked_at']);
  }

  private function now(): string
  {
    return $this->clock->now()->format(Clock::STORED);
  }
}
