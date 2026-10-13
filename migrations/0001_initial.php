<?php

declare(strict_types=1);

/* The shape frozen for `1.0`: the statements an empty database `Storage\Schema`
   made, copied out of `sqlite_master` and never edited again. The partial index
   names the types that answered on every locale then, and the long lines are
   wrapped where whitespace is nothing to SQL. A database this script has moved
   holds exactly what `Schema` declared then, as `differences()` tells; a type or
   table added after is a later script. */
return function (\PDO $db): void {
  $statements = [
    <<<'SQL'
    CREATE TABLE entity (
      id             text primary key,
      type           text not null,
      status         text not null
                     check (status in ('draft','review','published','archived')),
      locale         text not null,
      translation_of text references entity(id),
      slug           text not null,
      version        integer not null default 1 check (version >= 1),
      publish_from     text,
      publish_to       text,
      created_at     text not null,
      updated_at     text not null,
      deleted_at     text,
      sort_keys      text not null check (json_valid(sort_keys)),
      props          text not null check (json_valid(props)),
      words          text not null check (json_valid(words)),
      unique (type, locale, slug)
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE entity_word (
      entity_id text not null references entity(id) on delete cascade,
      field     text not null,
      word      text not null
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE relation (
      id         text primary key,
      subject_id text not null references entity(id) on delete cascade,
      predicate  text not null,
      object_id  text not null references entity(id) on delete cascade,
      position   integer check (position >= 1),
      props      text check (props is null or json_valid(props)),
      unique (subject_id, predicate, object_id)
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE activity (
      id            text primary key,
      actor_id      text,
      actor_kind    text check (actor_kind in ('person','token')),
      actor_name    text,
      action_type   text not null,
      target_id     text,
      object_id     text,
      start_time    text,
      result        text check (result is null or json_valid(result)),
      check ((actor_id is null) = (actor_kind is null))
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE collection (
      id        text primary key,
      type      text not null unique,
      fields    text not null check (json_valid(fields))
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE account (
      id            text primary key,
      person_id     text not null unique references entity(id),
      email         text not null unique,
      role          text not null check (role in ('owner','editor','viewer')),
      write_scope   text check (write_scope <> ''),
      read_scope    text check (read_scope <> ''),
      created_at    text not null,
      last_login_at text,
      disabled_at   text
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE login_code (
      id          text primary key,
      account_id  text not null references account(id) on delete cascade,
      code_hash   text not null,
      session_key text not null,
      issued_at   text not null,
      expires_at  text not null,
      attempts    integer not null default 0
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE setting (
      key        text primary key,
      value      text not null check (json_valid(value)),
      updated_at text not null
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE rate_limit (
      id        text primary key,
      bucket    text not null,
      ends_at   text not null,
      count     integer not null,
      unique (bucket, ends_at)
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE token (
      id           text primary key,
      label        text not null,
      token_hash   text not null unique,
      created_at   text not null,
      created_by   text not null,
      expires_at   text not null,
      last_used_at text,
      revoked_at   text
    ) strict
    SQL,
    <<<'SQL'
    CREATE TABLE grant_rule (
      id         text primary key,
      token_id   text not null references token(id) on delete cascade,
      collection text not null,
      field      text,
      subject_id text,
      capability text not null check (capability in ('create','set','read','arrange')),
      unpublished integer not null default 0
                 check (unpublished in (0, 1) and (unpublished = 0 or capability = 'read')),
      created_at text not null
    ) strict
    SQL,
    <<<'SQL'
    CREATE INDEX entity_type on entity (type)
    SQL,
    <<<'SQL'
    CREATE INDEX entity_slug on entity (type, slug)
    SQL,
    <<<'SQL'
    CREATE INDEX entity_translation on entity (translation_of)
    SQL,
    <<<'SQL'
    CREATE INDEX entity_word_prefix on entity_word (word, field, entity_id)
    SQL,
    <<<'SQL'
    CREATE INDEX entity_word_entity on entity_word (entity_id)
    SQL,
    <<<'SQL'
    CREATE INDEX relation_object on relation (object_id, predicate)
    SQL,
    <<<'SQL'
    CREATE INDEX activity_time on activity (start_time, id)
    SQL,
    <<<'SQL'
    CREATE UNIQUE INDEX entity_slug_across_locales on entity (type, slug)
      where type in ('https://schema.org/Action', 'https://schema.org/Brand',
        'https://schema.org/Comment', 'https://schema.org/Course', 'https://schema.org/Dataset',
        'https://schema.org/DefinedTerm', 'https://schema.org/Event',
        'https://schema.org/Invoice', 'https://schema.org/LocalBusiness',
        'https://schema.org/MediaObject', 'https://schema.org/MemberProgram',
        'https://schema.org/Message', 'https://schema.org/Offer', 'https://schema.org/Order',
        'https://schema.org/Organization', 'https://schema.org/Person',
        'https://schema.org/Place', 'https://schema.org/Product',
        'https://schema.org/Reservation', 'https://schema.org/Review',
        'https://schema.org/Service', 'https://schema.org/SoftwareApplication')
    SQL,
    <<<'SQL'
    CREATE TRIGGER entity_word_insert after insert on entity begin
      insert into entity_word (entity_id, field, word) select new.id, f.key, w.value
        from json_each(new.words) f, json_each(f.value) w;
    end
    SQL,
    <<<'SQL'
    CREATE TRIGGER entity_word_update after update of words on entity begin
      delete from entity_word where entity_id = new.id;
      insert into entity_word (entity_id, field, word) select new.id, f.key, w.value
        from json_each(new.words) f, json_each(f.value) w;
    end
    SQL,
  ];
  foreach ($statements as $statement) {
    $db->exec($statement);
  }
};
