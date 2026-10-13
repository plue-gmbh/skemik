<?php

declare(strict_types=1);

namespace Storage;

use Auth\AccountRefused;
use Auth\AccountRepository;
use Domain\ActionType;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\TargetRequired;
use Domain\Props;
use Domain\Relation;
use Profile\Profile;
use Profile\SchemaOrg;
use Support\Clock;

/* The trash, the way out of it, and the purge. Trash is an undo keeping slug,
   numbers and edges, so restoring never collides; a purge is the removal, and
   what it takes out of every other record is a change of that record: its
   version moves and its log names the edge that left, in the purge's
   transaction, so a colleague's open record and a channel's `If-Match` see it.
   A record a required edge names is refused a purge, naming the records that
   need it, since a row left failing its type is one every later save refuses
   for a field nobody touched. Both refuse a `Person` holding an account. A
   purged root of a translation group leaves the oldest member as the root the
   others name. Several go in, come out and are purged in one transaction, so
   one refused leaves the lot as it was; an edge from one of the lot to another
   leaves with both, logged on neither and binding nothing. */
final readonly class Trash
{
  public function __construct(
    private \PDO $db,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private AccountRepository $accounts,
    private ActivityLog $log,
    private Profile $profile,
    private SchemaOrg $schema,
    private Clock $clock,
    private Transaction $transaction = new Transaction(),
  ) {}

  public function delete(EntityId ...$ids): void
  {
    $this->transaction->run($this->db, function () use ($ids): void {
      foreach ($ids as $id) {
        $this->requireUnheld($id);
        $this->moveDeletedAt($this->entities->find($id), $this->now());
      }
    });
  }

  public function restore(EntityId ...$ids): void
  {
    $this->transaction->run($this->db, function () use ($ids): void {
      foreach ($ids as $id) {
        $this->moveDeletedAt($this->entities->find($id), null);
      }
    });
  }

  public function purge(EntityId ...$ids): void
  {
    $lot = array_map(fn (EntityId $id): string => $id->value, $ids);
    $this->transaction->run($this->db, function () use ($ids, $lot): void {
      foreach ($ids as $id) {
        $this->purgeOne($id, $lot);
      }
    });
  }

  /** @param list<string> $lot the ids going with it, whose edges to it leave unlogged and bind nothing */
  private function purgeOne(EntityId $id, array $lot): void
  {
    $purged = $this->entities->find($id);
    $this->requireUnheld($id);
    $leaving = $this->leaving($purged, $lot);
    $this->reroot($id);
    $this->db->prepare('delete from entity where id = :id')
      ->execute(['id' => $id->value]);
    foreach ($leaving as $subject => $edges) {
      $this->moved(new EntityId($subject), $edges);
    }
    $this->log->record(ActionType::Delete, $id, new Props(['type' => $purged->typeName()]));
  }

  private function requireUnheld(EntityId $id): void
  {
    $account = $this->accounts->heldBy($id);
    if ($account !== null) {
      throw AccountRefused::holdsPerson($account);
    }
  }

  /* Trash and its undo are updates, not deletions, and say which way they went. */
  private function moveDeletedAt(Entity $entity, ?string $to): void
  {
    if ($entity->deletedAt === $to || ($entity->deletedAt !== null && $to !== null)) {
      return;
    }
    $this->db->prepare('update entity set deleted_at = :to, updated_at = :now,'
      . ' version = version + 1 where id = :id')
      ->execute(['to' => $to, 'now' => $this->now(), 'id' => $entity->id->value]);
    $moved = new Props(['deleted_at' => ['from' => $entity->deletedAt, 'to' => $to]]);
    $this->log->record(ActionType::Update, $entity->id, $moved);
  }

  /* Every record an edge points from, each with the edges it loses, or the
     refusal naming the ones a required edge binds to the record; one going in
     the same lot binds nothing.
     @param list<string> $lot
     @return array<string, array<string, array{from: string, to: null}>> by subject, then predicate */
  private function leaving(Entity $purged, array $lot): array
  {
    $address = self::address($purged);
    $leaving = $required = [];
    foreach ($this->relations->inbound($purged->id) as $edge) {
      $leaving[$edge->subject->value][$edge->predicate] = ['from' => $address, 'to' => null];
    }
    $leaving = array_diff_key($leaving, array_flip($lot));
    foreach ($leaving as $subject => $edges) {
      $record = $this->entities->find(new EntityId($subject));
      if ($this->requires($record, $purged->id, array_keys($edges))) {
        $required[] = self::address($record);
      }
    }

    return $required === [] ? $leaving : throw TargetRequired::by($address, $required);
  }

  /* Whether one of the predicates is a required field the record would hold
     no other target of.
     @param list<string> $predicates */
  private function requires(Entity $record, EntityId $target, array $predicates): bool
  {
    $held = $this->relations->outboundByPredicate($record->id);
    foreach ($this->profile->byType($record->type)->edges() as $field) {
      $predicate = (string) $this->schema->propertyName($field->key);
      $others = array_filter($held[$predicate] ?? [], fn (Relation $edge): bool
        => $edge->object->value !== $target->value);
      if ($field->required && in_array($predicate, $predicates, true) && $others === []) {
        return true;
      }
    }

    return false;
  }

  /** @param array<string, array{from: string, to: null}> $edges by predicate, as the log keeps them */
  private function moved(EntityId $subject, array $edges): void
  {
    $this->db->prepare('update entity set version = version + 1, updated_at = :now where id = :id')
      ->execute(['now' => $this->now(), 'id' => $subject->value]);
    $this->log->record(ActionType::Update, $subject, new Props($edges));
  }

  /* Members name the root and never each other (`Domain\TranslationGroup`), so
     the oldest remaining member becomes the root the others name. */
  private function reroot(EntityId $root): void
  {
    $statement = $this->db->prepare('select id from entity where translation_of = :root'
      . ' order by id limit 1');
    $statement->execute(['root' => $root->value]);
    $heir = $statement->fetchColumn();
    if ($heir === false) {
      return;
    }
    $this->db->prepare('update entity set translation_of = case when id = :heir then null'
      . ' else :heir end where translation_of = :root')
      ->execute(['heir' => $heir, 'root' => $root->value]);
  }

  /* The address a document names a record by, less the origin. */
  private static function address(Entity $record): string
  {
    return Collection::SLUGS[$record->typeName()] . '/' . $record->slug->value;
  }

  private function now(): string
  {
    return $this->clock->now()->format(Clock::STORED);
  }
}
