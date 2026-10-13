<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Domain\Status;
use Support\Json;

/* What the home screen's Needs a look counts over the rows (`Admin\NeedsLook`),
   one grouped query a finding rather than a record loaded per row, since a house
   with three hundred drafts should not pay for them on every visit to the
   overview. Trashed rows are left out, and each answer is by type, most first,
   the collection a finding opens being the one holding most of it. */
final readonly class Oversights
{
  private const LIVE = 'e.deleted_at is null';

  public function __construct(private \PDO $db, private Json $json = new Json()) {}

  /**
   * @param list<string> $types the type URLs asked
   * @param ?string $before only rows made before this instant
   * @return array<string, int> by type URL
   */
  public function inState(Status $status, array $types, ?string $before = null): array
  {
    return $this->counted('select e.type, count(*) from entity e where ' . self::LIVE
      . ' and e.status = :status and e.type in (select value from json_each(:types))'
      . ' and (:before is null or e.created_at < :before)',
      ['status' => $status->value, 'types' => $this->json->encode($types), 'before' => $before]);
  }

  /* An image whose own words describe nothing, which is its alt text.
     @return array<string, int> by type URL */
  public function withoutAlt(string $type): array
  {
    return $this->counted('select e.type, count(*) from entity e where ' . self::LIVE . ' and e.type = :type'
      . " and substr(json_extract(e.props, '$.encoding_format'), 1, 6) = 'image/'"
      . " and trim(coalesce(json_extract(e.props, '$.description'), '')) = ''", ['type' => $type]);
  }

  /* The records a report named, among the types asked and out of the trash, each
     marked where it changed at or after `$since`, since only those can have been
     mended since the report was written.
     @param list<EntityId> $ids
     @param list<string> $types the type URLs asked
     @return array<string, array<string, bool>> by type URL, then by record id */
  public function listed(array $ids, array $types, string $since): array
  {
    $statement = $this->db->prepare('select e.type, e.id, e.updated_at >= :since from entity e where '
      . self::LIVE . ' and e.id in (select value from json_each(:ids))'
      . ' and e.type in (select value from json_each(:types)) order by e.id');
    $statement->execute(['since' => $since,
      'types' => $this->json->encode($types),
      'ids' => $this->json->encode(array_map(fn (EntityId $id): string => $id->value, $ids))]);
    $listed = [];
    foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$type, $id, $changed]) {
      $listed[(string) $type][(string) $id] = (bool) $changed;
    }

    return $listed;
  }

  /* A message nobody marked read, in any state, the oldest first, since an id
     is a UUIDv7 and the one waiting longest is the one to open.
     @return array<string, list<EntityId>> by type URL */
  public function unread(string $type): array
  {
    $statement = $this->db->prepare('select e.id from entity e where ' . self::LIVE . ' and e.type = :type'
      . " and json_extract(e.props, '$.date_read') is null order by e.id");
    $statement->execute(['type' => $type]);
    $ids = array_map(fn (string $id): EntityId => new EntityId($id), $statement->fetchAll(\PDO::FETCH_COLUMN));

    return $ids === [] ? [] : [$type => $ids];
  }

  /* A purge takes its edges with it, so what points into nothing points into the trash.
     @param list<string> $types the type URLs asked, of the record and of its target alike
     @return array<string, list<EntityId>> by type URL */
  public function pointingIntoTrash(array $types): array
  {
    $statement = $this->db->prepare('select e.type, e.id from entity e where ' . self::LIVE
      . ' and e.type in (select value from json_each(:types)) and exists (select 1 from relation r'
      . ' join entity t on t.id = r.object_id where r.subject_id = e.id'
      . ' and t.deleted_at is not null'
      . ' and t.type in (select value from json_each(:types))) order by e.type, e.id');
    $statement->execute(['types' => $this->json->encode($types)]);
    $ids = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $ids[(string) $row['type']][] = new EntityId((string) $row['id']);
    }
    uasort($ids, fn (array $one, array $other): int => count($other) <=> count($one));

    return $ids;
  }

  /**
   * @param array<string, string|null> $bindings
   * @return array<string, int>
   */
  private function counted(string $select, array $bindings): array
  {
    $statement = $this->db->prepare($select . ' group by e.type order by count(*) desc, e.type');
    $statement->execute([...$bindings]);

    return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_KEY_PAIR));
  }
}
