<?php

declare(strict_types=1);

/* The word table a sixth of its size: a record and a field are numbers, read
   back through two tables of their own, and a row is stored once, in its key,
   with the times its word stands there. The rows are filled from `words`, which
   they follow, and not from the old table. Frozen as `Storage\Schema` made it,
   and never edited again. */
return function (\PDO $db): void {
  $statements = [
    'DROP TRIGGER entity_word_insert',
    'DROP TRIGGER entity_word_update',
    'DROP TABLE entity_word',
    <<<'SQL'
    CREATE TABLE word_record (
      number    integer primary key,
      entity_id text not null unique,
      type      text not null
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE word_field (
      number integer primary key,
      name   text not null unique
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE entity_word (
      word   text not null,
      field  integer not null,
      record integer not null,
      times  integer not null,
      primary key (word, field, record)
    ) strict, without rowid
    SQL,
    'INSERT INTO word_record (entity_id, type) SELECT id, type FROM entity ORDER BY id',
    'INSERT INTO word_field (name) SELECT DISTINCT f.key FROM entity e, json_each(e.words) f ORDER BY 1',
    <<<'SQL'
    INSERT INTO entity_word (word, field, record, times)
      SELECT w.value, n.number, r.number, count(*) FROM word_record r
        CROSS JOIN entity e ON e.id = r.entity_id CROSS JOIN json_each(e.words) f
        CROSS JOIN json_each(f.value) w CROSS JOIN word_field n ON n.name = f.key
      GROUP BY w.value, n.number, r.number ORDER BY 1, 2, 3
    SQL,
    <<<'SQL'
    CREATE TRIGGER entity_word_insert after insert on entity begin
      insert into word_record (entity_id, type) values (new.id, new.type);
      insert or ignore into word_field (name) select key from json_each(new.words);
      insert into entity_word (word, field, record, times) select w.value, n.number,
        (select number from word_record where entity_id = new.id), count(*) from json_each(new.words) f
        cross join json_each(f.value) w cross join word_field n on n.name = f.key group by w.value, n.number;
    end
    SQL,
    <<<'SQL'
    CREATE TRIGGER entity_word_update after update of words on entity begin
      delete from entity_word where record = (select number from word_record where entity_id = old.id)
        and (word, field) in (select w.value, n.number from json_each(old.words) f
        cross join json_each(f.value) w cross join word_field n on n.name = f.key);
      insert or ignore into word_field (name) select key from json_each(new.words);
      insert into entity_word (word, field, record, times) select w.value, n.number,
        (select number from word_record where entity_id = new.id), count(*) from json_each(new.words) f
        cross join json_each(f.value) w cross join word_field n on n.name = f.key group by w.value, n.number;
    end
    SQL,
    <<<'SQL'
    CREATE TRIGGER entity_word_delete after delete on entity begin
      delete from entity_word where record = (select number from word_record where entity_id = old.id)
        and (word, field) in (select w.value, n.number from json_each(old.words) f
        cross join json_each(f.value) w cross join word_field n on n.name = f.key);
      delete from word_record where entity_id = old.id;
    end
    SQL,
  ];
  foreach ($statements as $statement) {
    $db->exec($statement);
  }
};
