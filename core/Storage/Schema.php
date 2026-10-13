<?php

declare(strict_types=1);

namespace Storage;

use Profile\Profile;

/* The shape, declared once and as the code, so it cannot go stale against it; a
   generated `schema.sql` beside it would be a third copy going stale while looking
   authoritative. `create` refuses a database that is not empty, since `create
   table if not exists` is a no-op against a table of another shape and the
   mismatch would surface on a customer's data. The slug index across locales
   reads the profile, so a release changing a type's `LocaleMode` needs a
   migration; `sort_keys` and `words` need none, every update writing them anew.
   `props` and `words` come last in `entity`: a filter reading `status` past
   kilobytes of text follows overflow pages first. */
final readonly class Schema
{
  private const TABLES = [
    <<<'SQL'
    create table entity (
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
    create table entity_word (
      entity_id text not null references entity(id) on delete cascade,
      field     text not null,
      word      text not null
    ) strict
    SQL,
    <<<'SQL'
    create table relation (
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
    create table activity (
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
    create table collection (
      id        text primary key,
      type      text not null unique,
      fields    text not null check (json_valid(fields))
    ) strict
    SQL,
    <<<'SQL'
    create table account (
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
    create table login_code (
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
    create table setting (
      key        text primary key,
      value      text not null check (json_valid(value)),
      updated_at text not null
    ) strict
    SQL,
    <<<'SQL'
    create table rate_limit (
      id        text primary key,
      bucket    text not null,
      ends_at   text not null,
      count     integer not null,
      unique (bucket, ends_at)
    ) strict
    SQL,
    <<<'SQL'
    create table token (
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
    create table grant_rule (
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
  ];

  private const INDEXES = [
    'create index entity_type on entity (type)',
    'create index entity_slug on entity (type, slug)',
    'create index entity_translation on entity (translation_of)',
    'create index entity_word_prefix on entity_word (word, field, entity_id)',
    'create index entity_word_entity on entity_word (entity_id)',
    'create index relation_object on relation (object_id, predicate)',
    'create index activity_time on activity (start_time, id)',
  ];

  public function __construct(private Profile $profile, private Shape $shape = new Shape()) {}

  /* `$version` is the script number this shape equals, in the same transaction:
     `Shape` cannot see the pragma, so a stamp missed here is never noticed. */
  public function create(\PDO $db, int $version): void
  {
    if (!$this->empty($db)) {
      throw SchemaFailed::notEmpty();
    }
    $db->beginTransaction();
    foreach ($this->statements() as $statement) {
      $db->exec($statement);
    }
    $db->exec('pragma user_version = ' . $version);
    $db->commit();
  }

  public function empty(\PDO $db): bool
  {
    return (int) $db->query('select count(*) from sqlite_master')->fetchColumn() === 0;
  }

  /** @return list<string> empty when the database holds exactly the declared shape */
  public function differences(\PDO $db): array
  {
    $expected = $this->shape->of($this->declared());
    $actual = $this->shape->of($db);
    $found = [];
    foreach ([...$expected, ...$actual] as $part => $unused) {
      $want = $expected[$part] ?? 'nothing';
      $have = $actual[$part] ?? 'nothing';
      if ($want !== $have) {
        $found[] = sprintf('%s: declared %s, found %s', $part, $want, $have);
      }
    }

    return $found;
  }

  private function declared(): \PDO
  {
    $db = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $this->create($db, 0);

    return $db;
  }

  /** @return list<string> */
  private function statements(): array
  {
    return [...self::TABLES, ...self::INDEXES, $this->slugAcrossLocales(), ...$this->search()];
  }

  /* The word table follows `words` by triggers, one row per word per field, so
     a save writes the JSON once and never the rows; a purge takes them by key.
     @return list<string> */
  private function search(): array
  {
    $insert = 'insert into entity_word (entity_id, field, word) select new.id, f.key, w.value'
      . ' from json_each(new.words) f, json_each(f.value) w';

    return ["create trigger entity_word_insert after insert on entity begin $insert; end",
      "create trigger entity_word_update after update of words on entity begin"
      . " delete from entity_word where entity_id = new.id; $insert; end"];
  }

  private function slugAcrossLocales(): string
  {
    $urls = [];
    foreach ($this->profile->types() as $type) {
      if ($type->locale->answersOnEveryLocale()) {
        $urls[] = "'" . $type->url() . "'";
      }
    }
    sort($urls);

    return sprintf('create unique index entity_slug_across_locales on entity (type, slug)'
      . ' where type in (%s)', implode(', ', $urls));
  }
}
