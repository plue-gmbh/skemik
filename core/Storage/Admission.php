<?php

declare(strict_types=1);

namespace Storage;

/* Nobody gets in after a restore who could not before it: a snapshot puts the
   holdings back, never an account or a key the house removed since, nor a role,
   a scope, an address or a rule it took back. So an account or a key the copy
   holds is kept only where the live house still holds it, as the house has it,
   and the owner restoring keeps the account they restore with, their person
   record brought along where the copy lacks it or holds it in the trash. Read
   from the live database, attached, onto a copy already at the live shape, and
   by each column's name, since a migrated table may order them otherwise. */
final readonly class Admission
{
  public function carry(\PDO $restored, string $live, string $owner): void
  {
    $restored->prepare('attach database ? as live')->execute([$live]);
    try {
      $restored->beginTransaction();
      $this->admitted($restored, $owner);
      $restored->commit();
    } catch (\Throwable $failure) {
      $restored->inTransaction() && $restored->rollBack();
      throw $failure;
    } finally {
      $restored->exec('detach database live');
    }
  }

  /* A key's rules go with it, and a live key holds no rule its copy lacks, a
     grant being narrowed and never widened. */
  private function admitted(\PDO $db, string $owner): void
  {
    $accounts = (string) $db->query('select json_group_array(id) from main.account')->fetchColumn();
    $tokens = (string) $db->query('select json_group_array(id) from main.token')->fetchColumn();
    $db->exec('delete from main.account');
    $db->exec('delete from main.token');
    $this->person($db, $owner);
    $this->copied($db, 'account', 'id in (select value from json_each(:held)) or id = :owner',
      ['held' => $accounts, 'owner' => $owner]);
    $this->copied($db, 'token', 'id in (select value from json_each(:held))', ['held' => $tokens]);
    $this->copied($db, 'grant_rule', 'token_id in (select value from json_each(:held))', ['held' => $tokens]);
  }

  private function person(\PDO $db, string $owner): void
  {
    $person = '(select person_id from live.account where id = :owner)';
    $copied = $this->copied($db, 'entity', 'id = ' . $person . ' and id not in (select id from main.entity)',
      ['owner' => $owner]);
    if ($copied > 0) {
      $this->copied($db, 'entity_word', 'entity_id = ' . $person, ['owner' => $owner]);
    }
    $db->prepare('update main.entity set deleted_at = null where id = ' . $person)->execute(['owner' => $owner]);
  }

  /** @param array<string, string> $values */
  private function copied(\PDO $db, string $table, string $where, array $values): int
  {
    $columns = implode(', ', array_column($db->query("pragma main.table_info('" . $table . "')")->fetchAll(),
      'name'));
    $statement = $db->prepare(sprintf('insert into main.%1$s (%2$s) select %2$s from live.%1$s where %3$s',
      $table, $columns, $where));
    $statement->execute($values);

    return $statement->rowCount();
  }
}
