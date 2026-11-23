<?php

declare(strict_types=1);

/* The three values held once per scope, an order's number, an invoice's and a
   term's code, each a generated column of `entity` with its index, so a new
   record asks the index and not every record of its type. Virtual, so a row
   grows by nothing but the index. Frozen as `Storage\Schema` made it, and never
   edited again. */
return function (\PDO $db): void {
  $statements = [
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_order_number any generated always as (case when type in
      ('https://schema.org/Order') then json_extract(props, '$.order_number') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_identifier any generated always as (case when type in
      ('https://schema.org/Invoice') then json_extract(props, '$.identifier') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_term_code any generated always as (case when type in
      ('https://schema.org/DefinedTerm') then json_extract(props, '$.term_code') end) virtual
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_order_number on entity (type, prop_order_number, deleted_at) where
      prop_order_number is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_identifier on entity (type, prop_identifier, deleted_at) where prop_identifier
      is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_term_code on entity (type, prop_term_code, deleted_at) where prop_term_code is
      not null
    SQL,
  ];
  foreach ($statements as $statement) {
    $db->exec($statement);
  }
};
