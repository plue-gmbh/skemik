<?php

declare(strict_types=1);

namespace Storage;

/* A database's structure as comparable lines, read back through the pragmas and
   never from the stored SQL text, which `alter table` rewrites: a migration that
   reaches the declared shape by another spelling still reaches it. Triggers and
   views have no pragma, so their text is compared with whitespace folded.
   What it cannot see is a `check` clause, which no pragma reports. */
final readonly class Shape
{
  /** @return array<string, string> one line per table, column, key, index and trigger */
  public function of(\PDO $db): array
  {
    $shape = [];
    foreach ($db->query("pragma table_list")->fetchAll(\PDO::FETCH_ASSOC) as $table) {
      if ($table['schema'] === 'main' && !str_starts_with($table['name'], 'sqlite_')) {
        $shape = [...$shape, ...$this->tableShape($db, $table['name'], $table['type'], $table['strict'])];
      }
    }
    $sql = "select type, name, sql from sqlite_master where type in ('trigger', 'view')";
    foreach ($db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $object) {
      $shape[$object['type'] . ' ' . $object['name']] = (string) preg_replace('/\s+/', ' ', $object['sql']);
    }

    return $shape;
  }

  /** @return array<string, string> */
  private function tableShape(\PDO $db, string $table, string $type, int $strict): array
  {
    $shape = ["table $table" => $type . ($strict === 1 ? ' strict' : '')];
    foreach ($this->pragma($db, 'table_xinfo', $table) as $column) {
      $shape["table $table column {$column['name']}"] = $this->describe($column, ['cid', 'name']);
    }
    foreach ($this->pragma($db, 'foreign_key_list', $table) as $key) {
      $shape["table $table foreign key {$key['from']}"] = $this->describe($key, ['id', 'seq', 'from']);
    }
    foreach ($this->pragma($db, 'index_list', $table) as $index) {
      $columns = array_column($this->pragma($db, 'index_info', $index['name']), 'name');
      $unique = $index['unique'] === 1 ? 'unique ' : '';
      $partial = $index['partial'] === 1 ? $this->indexSql($db, $index['name']) : '';
      $name = $index['origin'] === 'c' ? $index['name'] : implode(', ', $columns);
      $shape["table $table index $name"] = $unique . implode(', ', $columns) . $partial;
    }

    return $shape;
  }

  /** @return list<array<string, string|int|null>> */
  private function pragma(\PDO $db, string $pragma, string $name): array
  {
    $statement = $db->prepare(sprintf('select * from pragma_%s(?)', $pragma));
    $statement->execute([$name]);

    return $statement->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param array<string, string|int|null> $row
   * @param list<string> $ignored
   */
  private function describe(array $row, array $ignored): string
  {
    $kept = array_diff_key($row, array_flip($ignored));

    return implode(' ', array_map(fn (string $key, string|int|null $value): string =>
      $key . '=' . var_export($value, true), array_keys($kept), $kept));
  }

  private function indexSql(\PDO $db, string $index): string
  {
    $statement = $db->prepare("select sql from sqlite_master where type = 'index' and name = ?");
    $statement->execute([$index]);
    $where = (string) stristr((string) $statement->fetchColumn(), ' where ');

    return (string) preg_replace('/\s+/', ' ', $where);
  }
}
