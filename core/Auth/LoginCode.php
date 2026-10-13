<?php

declare(strict_types=1);

namespace Auth;

use Storage\Transaction;
use Support\Clock;
use Support\Uuid;

/* The whole human credential; there is no password, and the reset is the
   login. The only reader and writer of `login_code`. A code is found by the session
   that asked for it and never by itself, which is what binds it to that browser:
   one read aloud over the phone logs in nobody else. One outstanding per session,
   not per account, so a stranger asking for the owner's address ends no code of
   hers. Ten minutes, burned on success and on the fifth wrong attempt. Eight
   characters, because asking is bounded per client and not per address, so
   whoever holds many clients holds many codes to guess at. It is taken exactly
   as sent. Only hashes are stored, of
   the code and of the session key. `redeem` gives one answer for every refusal,
   since which one applied tells a stranger whether the address holds an account. */
final readonly class LoginCode
{
  private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  private const LENGTH = 8;
  public const MINUTES = 10;
  private const ATTEMPTS = 5;

  public function __construct(
    private \PDO $db,
    private AccountRepository $accounts,
    private Uuid $uuid,
    private Clock $clock,
    private Transaction $transaction = new Transaction(),
  ) {}

  /* Ended codes of every account go with it, so nothing prunes on a schedule. */
  public function issue(Account $account, string $sessionKey): string
  {
    $code = $this->generate();
    $now = $this->clock->now();
    $this->transaction->run($this->db, function () use ($account, $sessionKey, $code, $now): void {
      $this->db->prepare('delete from login_code where (session_key = :session'
        . ' or expires_at <= :now)')
        ->execute(['session' => hash('sha256', $sessionKey),
          'now' => $now->format(Clock::STORED)]);
      $this->db->prepare('insert into login_code (id, account_id, code_hash, session_key,'
        . ' issued_at, expires_at) values (:id, :account, :code, :session, :issued, :expires)')
        ->execute(['id' => $this->uuid->generate(),
          'account' => $account->id, 'code' => hash('sha256', $code),
          'session' => hash('sha256', $sessionKey),
          'issued' => $now->format(Clock::STORED),
          'expires' => $now->modify(sprintf('+%d minutes', self::MINUTES))->format(Clock::STORED)]);
    });

    return $code;
  }

  public function redeem(string $typed, string $sessionKey): ?Account
  {
    $accountId = null;
    $this->transaction->run($this->db, function () use ($typed, $sessionKey, &$accountId): void {
      $statement = $this->db->prepare('select id, account_id, code_hash, attempts from login_code'
        . ' where session_key = :session and expires_at > :now');
      $statement->execute(['session' => hash('sha256', $sessionKey),
        'now' => $this->clock->now()->format(Clock::STORED)]);
      $row = $statement->fetch(\PDO::FETCH_ASSOC);
      if ($row === false) {
        return;
      }
      $matches = hash_equals((string) $row['code_hash'], hash('sha256', $typed));
      $accountId = $matches ? (string) $row['account_id'] : null;
      $this->burnOrCount((string) $row['id'], $matches || (int) $row['attempts'] + 1 >= self::ATTEMPTS);
    });
    $account = $accountId === null ? null : $this->accounts->find($accountId);

    return $account !== null && $account->disabledAt === null ? $account : null;
  }

  /* The codes on their way to an account, which went to the address it had. */
  public function forget(Account $account): void
  {
    $this->db->prepare('delete from login_code where account_id = :account')
      ->execute(['account' => $account->id]);
  }

  /* No 0/O and no 1/I, since the code is read off one screen and typed into another. */
  private function generate(): string
  {
    $code = '';
    for ($i = 0; $i < self::LENGTH; $i++) {
      $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
    }

    return $code;
  }

  private function burnOrCount(string $id, bool $burn): void
  {
    $sql = $burn ? 'delete from login_code' : 'update login_code set attempts = attempts + 1';
    $this->db->prepare($sql . ' where id = :id')
      ->execute(['id' => $id]);
  }
}
