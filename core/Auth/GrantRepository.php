<?php

declare(strict_types=1);

namespace Auth;

use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Slug;
use Storage\Transaction;

/* Reads and writes `grant_rule`, and holds the two rules that tie a token's life
   to its rules: a token is issued together with at least one, and removing its
   last one revokes it, since a live secret that may do nothing is a credential
   nobody remembers to revoke and every scanner finds. There is no method that
   adds a rule to an issued token: a live grant is narrowed and never widened, so
   a second thing is a new token. Default deny: no covering rule is a
   refusal, and refusal is the normal case. */
final readonly class GrantRepository
{
  public function __construct(
    private \PDO $db,
    private TokenRepository $tokens,
    private Transaction $transaction = new Transaction(),
  ) {}

  /**
   * @param list<Grant> $rules
   * @return string the secret, shown once
   */
  public function issue(Token $token, array $rules): string
  {
    $foreign = array_filter($rules, fn (Grant $rule): bool => $rule->tokenId !== $token->id);
    if ($rules === [] || $foreign !== []) {
      throw MalformedValue::named('rule list of this token', $token->label);
    }
    $secret = '';
    $this->transaction->run($this->db, function () use ($token, $rules, &$secret): void {
      $secret = $this->tokens->insert($token);
      array_walk($rules, $this->insert(...));
    });

    return $secret;
  }

  /** @return list<Grant> */
  public function forToken(Token $token): array
  {
    $statement = $this->db->prepare('select * from grant_rule'
      . ' where token_id = :token order by collection, field, id');
    $statement->execute(['token' => $token->id]);

    return array_map($this->grant(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  /* The rules written for one collection, whatever token holds them: what its
     badges draw.
     @return list<Grant> */
  public function forCollection(Slug $collection): array
  {
    $statement = $this->db->prepare('select * from grant_rule'
      . ' where collection = :collection order by field, created_at, id');
    $statement->execute(['collection' => $collection->value]);

    return array_map($this->grant(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  /* Who writes into each collection at `$now`, by the names of the live keys
     holding a `create` or `set` rule there, in one query: what the sidebar marks.
     @return array<string, list<string>> by collection slug */
  public function writers(string $now): array
  {
    $statement = $this->db->prepare('select distinct g.collection, t.label from grant_rule g'
      . ' join token t on t.id = g.token_id'
      . " where g.capability in ('create', 'set') and t.revoked_at is null and t.expires_at > :now"
      . ' order by t.label');
    $statement->execute(['now' => $now]);
    $writers = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $writers[(string) $row['collection']][] = (string) $row['label'];
    }

    return $writers;
  }

  /* The live keys whose date falls between the two instants, each with the first
     collection it holds a rule in, where its badge is found. Only a name's newest
     live key counts: an older one is left to run out by a re-issue, which is the
     renewal, and a key issued under a live name takes its spelling, so one name
     is one label here.
     @return array<string, string> collection slug by token id */
  public function expiring(string $now, string $until): array
  {
    $statement = $this->db->prepare('select t.id, min(g.collection) from token t join grant_rule g'
      . ' on g.token_id = t.id'
      . ' where t.revoked_at is null and t.expires_at > :now and t.expires_at <= :until'
      . ' and not exists (select 1 from token n where n.label = t.label'
      . ' and n.id > t.id and n.revoked_at is null and n.expires_at > :now) group by t.id');
    $statement->execute(['now' => $now, 'until' => $until]);

    return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
  }

  public function remove(Grant $rule): void
  {
    $this->transaction->run($this->db, function () use ($rule): void {
      $statement = $this->db->prepare('delete from grant_rule where id = :id');
      $statement->execute(['id' => $rule->id]);
      if ($statement->rowCount() === 0) {
        throw EntityNotFound::withId($rule->id);
      }
      $token = $this->tokens->find($rule->tokenId);
      if ($this->forToken($token) === []) {
        $this->tokens->revoke($token);
      }
    });
  }

  private function insert(Grant $rule): void
  {
    $this->db->prepare('insert into grant_rule (id, token_id, collection, field,'
      . ' subject_id, capability, unpublished, created_at) values (:id, :token, :collection,'
      . ' :field, :subject, :capability, :unpublished, :created_at)')
      ->execute(['id' => $rule->id, 'token' => $rule->tokenId,
        'collection' => $rule->collection->value, 'field' => $rule->field,
        'subject' => $rule->subject?->value, 'capability' => $rule->capability->value,
        'unpublished' => (int) $rule->unpublished, 'created_at' => $rule->createdAt]);
  }

  /** @param array<string, string|int|null> $row */
  private function grant(array $row): Grant
  {
    return new Grant((string) $row['id'], (string) $row['token_id'],
      new Slug((string) $row['collection']), GrantCapability::from((string) $row['capability']),
      (string) $row['created_at'], $row['field'] === null ? null : (string) $row['field'],
      $row['subject_id'] === null ? null : new EntityId((string) $row['subject_id']),
      (int) $row['unpublished'] === 1);
  }
}
