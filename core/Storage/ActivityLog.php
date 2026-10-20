<?php

declare(strict_types=1);

namespace Storage;

use Auth\Token;
use Domain\ActionType;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Props;
use Profile\FieldDefinition;
use Support\Clock;
use Support\Json;
use Support\Uuid;

/* Append-only writer for `activity`: no update and no delete, and the absence is
   the design. The actor is bound at construction, so no write path can attribute
   a change to somebody else. A save keeps the keys of what it changed, and
   a value only where nothing else could tell later, never an account's address.
   `actor_name` is written with the row, the last moment the name is certain to
   exist, so the log still says who changed a record after they have left. The
   two redactions are the only other writes, since an actor's name and a former
   slug are personal data an erasure has to reach: the owner's only, each nulling
   one value and never a row, and each logged by a row of its own. */
final readonly class ActivityLog
{
  /* A row that is about nothing, which only a redaction of a row about nothing
     would be, is no line anywhere. */
  private const LINES = ' from activity'
    . ' where coalesce(target_id, object_id) is not null';
  private const READ = 'select id, target_id, object_id, action_type, result, actor_id, actor_kind,'
    . ' actor_name, start_time' . self::LINES;

  public function __construct(
    private \PDO $db,
    private Entity|Token|null $actor,
    private Uuid $uuid,
    private Clock $clock,
    private Json $json = new Json(),
  ) {
    if ($actor instanceof Entity && $actor->typeName() !== 'Person') {
      throw MalformedValue::named('person acting', $actor->type);
    }
  }

  /* An account, a key or a language is no record, so its row names it by
     `object_id` and says which under `about`. */
  public function record(ActionType $type, EntityId|HouseObject|null $target, ?Props $result = null): void
  {
    $house = $target instanceof HouseObject;
    $kept = $house ? ['about' => $target->item->value, ...$result?->document() ?? []] : $result?->document();
    $this->db->prepare('insert into activity (id, actor_id, actor_kind, actor_name,'
      . ' action_type, target_id, object_id, start_time, result) values (:id, :actor_id,'
      . ' :actor_kind, :actor_name, :action_type, :target_id, :object_id, :now, :result)')
      ->execute([
        'id' => $this->uuid->generate(),
        ...$this->actorColumns(),
        'action_type' => $type->value,
        'target_id' => $house ? null : $target?->value,
        'object_id' => $house ? $target->id : null,
        'now' => $this->clock->now()->format(Clock::STORED),
        'result' => $kept === null ? null : $this->json->encode((object) $kept),
      ]);
  }

  /* A save records what it left behind and nothing can reconstruct afterwards,
     the slug a site was built from and a number no second record may hold, and
     the keys of the fields and spine settings it changed, never their values.
     @param list<FieldDefinition> $changed */
  public function recordSave(Entity $entity, ?Entity $stored, array $changed): void
  {
    if ($stored === null) {
      $this->record(ActionType::Create, $entity->id);

      return;
    }
    $held = ['slug' => [$stored->slug->value, $entity->slug->value]];
    foreach ($changed as $field) {
      if ($field->unique !== null) {
        $held[$field->key] = [$stored->props->string($field->key), $entity->props->string($field->key)];
      }
    }
    $left = array_filter($held, fn (array $pair): bool => $pair[0] !== $pair[1]);
    $result = array_map(fn (array $pair): array => ['from' => $pair[0], 'to' => $pair[1]], $left);
    $named = ['spine' => $entity->spineChanged($stored),
      'fields' => array_map(fn (FieldDefinition $field): string => $field->key, $changed)];
    $result = [...$result, ...array_filter($named)];
    $this->record(ActionType::Update, $entity->id, $result === [] ? null : new Props($result));
  }

  /* Who changed a record last, by the name kept on the row: what a `412` tells
     the person whose save lost, so overwriting a colleague is never silent. A
     redaction changes the log and not the record, so it is not the answer. */
  public function lastActorName(EntityId $target): ?string
  {
    $statement = $this->db->prepare('select actor_name from activity'
      . " where target_id = :target and json_extract(result, '$.redacted') is null"
      . ' order by start_time desc, id desc limit 1');
    $statement->execute(['target' => $target->value]);
    $name = $statement->fetchColumn();

    return $name === false || $name === null ? null : (string) $name;
  }

  /* The latest row on each record, account, key and language, newest first,
     read down the time index as it is fetched, so the home screen stops once it
     holds what it shows. A redaction is left out, as for `lastActorName`.
     @return \Generator<int, Activity> */
  public function latest(): \Generator
  {
    $statement = $this->db->prepare(self::READ . " and json_extract(result, '$.redacted') is null"
      . ' order by start_time desc, id desc');
    $statement->execute();
    $seen = [];
    while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
      $about = (string) ($row['target_id'] ?? $row['object_id']);
      if (!isset($seen[$about])) {
        $seen[$about] = true;
        yield $this->activity($row);
      }
    }
  }

  /* Every row, newest first, or one actor's: the log screen, a page at a time.
     @return list<Activity> */
  public function page(?string $actor, int $offset, int $limit): array
  {
    $statement = $this->db->prepare(self::READ . ($actor === null ? '' : ' and actor_id = :actor')
      . ' order by start_time desc, id desc limit :limit offset :offset');
    $statement->execute([...($actor === null ? [] : ['actor' => $actor]),
      'limit' => $limit, 'offset' => $offset]);

    return array_map($this->activity(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function count(?string $actor): int
  {
    $statement = $this->db->prepare('select count(*)' . self::LINES
      . ($actor === null ? '' : ' and actor_id = :actor'));
    $statement->execute([...($actor === null ? [] : ['actor' => $actor])]);

    return (int) $statement->fetchColumn();
  }

  /** @param array<string, string|null> $row */
  private function activity(array $row): Activity
  {
    $actor = $row['actor_id'];
    $result = $row['result'] === null ? null : new Props($this->json->decode($row['result']));

    return new Activity((string) $row['id'], $this->target($row, $result),
      ActionType::from((string) $row['action_type']), $result, (string) $row['start_time'],
      $row['actor_kind'] === 'person' ? $actor : null, $row['actor_kind'] === 'token' ? $actor : null,
      $row['actor_name']);
  }

  /** @param array<string, string|null> $row */
  private function target(array $row, ?Props $result): EntityId|HouseObject
  {
    return $row['target_id'] !== null ? new EntityId($row['target_id'])
      : new HouseObject(HouseItem::from((string) $result?->string('about')), (string) $row['object_id']);
  }

  /* How many writes an actor made since an instant: a grant's review says whether
     its token is still in use. */
  public function writesBy(string $actorId, string $since): int
  {
    $statement = $this->db->prepare('select count(*) from activity'
      . ' where actor_id = :actor and start_time >= :since');
    $statement->execute(['actor' => $actorId, 'since' => $since]);

    return (int) $statement->fetchColumn();
  }

  /* Whether a redaction of this person's name would touch anything. */
  public function namesActor(EntityId $person): bool
  {
    $statement = $this->db->prepare("select 1 from activity"
      . " where actor_id = :person and actor_kind = 'person' and actor_name is not null limit 1");
    $statement->execute(['person' => $person->value]);

    return $statement->fetchColumn() !== false;
  }

  /* Across every row naming that person; the redaction's own row names whoever redacts. */
  public function redactActorName(EntityId $person): void
  {
    $this->db->prepare("update activity set actor_name = null"
      . " where actor_id = :person and actor_kind = 'person'")
      ->execute(['person' => $person->value]);
    $this->record(ActionType::Update, $person, new Props(['redacted' => 'actor_name']));
  }

  /* One row's former slug, which can be a name (`people/max-mustermann`). The
     row keeps its target, so the redaction names the record it was about. */
  public function redactResult(string $activityId): void
  {
    if (preg_match(Uuid::SHAPE, $activityId) !== 1) {
      throw MalformedValue::named('activity id', $activityId);
    }
    $statement = $this->db->prepare('select target_id from activity where id = :id');
    $statement->execute(['id' => $activityId]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);
    if ($row === false) {
      throw EntityNotFound::withId($activityId);
    }
    $this->db->prepare('update activity set result = null where id = :id')
      ->execute(['id' => $activityId]);
    $about = $row['target_id'] === null ? null : new EntityId((string) $row['target_id']);
    $redaction = new Props(['redacted' => 'result', 'activity' => $activityId]);
    $this->record(ActionType::Update, $about, $redaction);
  }

  /** @return array{actor_id: ?string, actor_kind: ?string, actor_name: ?string} */
  private function actorColumns(): array
  {
    return match (true) {
      $this->actor instanceof Entity => ['actor_id' => $this->actor->id->value,
        'actor_kind' => 'person', 'actor_name' => $this->actor->props->string('name')],
      $this->actor instanceof Token => ['actor_id' => $this->actor->id,
        'actor_kind' => 'token', 'actor_name' => $this->actor->label],
      default => ['actor_id' => null, 'actor_kind' => null, 'actor_name' => null],
    };
  }
}
