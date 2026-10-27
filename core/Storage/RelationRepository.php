<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Props;
use Domain\Relation;
use Profile\Predicate;
use Support\Json;
use Support\Uuid;

/* Edges in both directions, and with `EntityRepository` the only class that
   writes SQL against its table; a target is written only when it is a row of
   the type and term the edge claims, since the foreign key alone would accept
   any row's id and the `Validator` judged the edge by its claim.
   Every `Relation` it returns says what its target is, read by the join that
   loads it, because `Profile\Validator` checks edges without a database and
   `Feed\JsonLd` emits a term by its code. `outbound` is the list the `Validator`
   is handed on the save path and in `HoldingsCheck` alike, so both ask the same
   question of the same shape. `inbound` and `inboundToGroup` are what
   `Admin\Showing` counts, read through `relation_object`. An edge under a
   predicate no type declares any more, left by a release that dropped the field,
   is read by nobody, since a `Relation` refuses it and a 500 on every screen
   mends nothing: `strays` names it to the holdings check, and the record's next
   save leaves it out, as `replace` writes the list a read gave. */
final readonly class RelationRepository
{
  private const SELECT = 'select r.subject_id, r.predicate, r.object_id, r.position,'
    . " r.props, t.type as object_type, json_extract(t.props, '$.term_code') as object_term"
    . ' from relation r join entity t on t.id = r.object_id'
    . ' where r.predicate in (select value from json_each(:predicates))';

  public function __construct(
    private \PDO $db,
    private Predicate $predicates,
    private Uuid $uuid,
    private Json $json = new Json(),
    private Transaction $transaction = new Transaction(),
  ) {}

  /** @return list<Relation> */
  public function outbound(EntityId $subject): array
  {
    return $this->select(' and r.subject_id = :id order by r.predicate, r.position, r.id', $subject);
  }

  /** @return array<string, list<Relation>> the same, by predicate and in their stored order */
  public function outboundByPredicate(EntityId $subject): array
  {
    $edges = [];
    foreach ($this->outbound($subject) as $edge) {
      $edges[$edge->predicate][] = $edge;
    }

    return $edges;
  }

  /** @return list<Relation> */
  public function inbound(EntityId $object): array
  {
    return $this->select(' and r.object_id = :id order by r.subject_id, r.predicate', $object);
  }

  /* To any member of a translation group, a trashed one too, whose link still serves the others.
     @return list<Relation> */
  public function inboundToGroup(EntityId $root): array
  {
    return $this->select(' and r.object_id in (select id from entity'
      . ' where id = :id or translation_of = :id) order by r.subject_id, r.predicate', $root);
  }

  /** @return list<string> the predicates of the subject's edges that no type declares, each once */
  public function strays(EntityId $subject): array
  {
    $statement = $this->db->prepare('select distinct predicate from relation where subject_id = :id'
      . ' and predicate not in (select value from json_each(:predicates)) order by predicate');
    $statement->execute(['id' => $subject->value, 'predicates' => $this->json->encode($this->predicates->names)]);

    return $statement->fetchAll(\PDO::FETCH_COLUMN);
  }

  /**
   * Every outbound edge of the subject, as it will stand: what the list leaves
   * out is removed. Inside the caller's transaction when there is one.
   *
   * @param list<Relation> $edges
   */
  public function replace(EntityId $subject, array $edges): void
  {
    $this->transaction->run($this->db, function () use ($subject, $edges): void {
      $this->requireRow($subject);
      $this->db->prepare('delete from relation where subject_id = :id')
        ->execute(['id' => $subject->value]);
      $written = [];
      foreach ($edges as $edge) {
        $this->requireOnce($edge, $written);
        $this->insert($subject, $edge);
      }
    });
  }

  /* The predicates whose edges `replace` would change, order and words included:
     none where it would leave them as they stand.
     @param list<Relation> $edges
     @return list<string> */
  public function changed(EntityId $subject, array $edges): array
  {
    $held = $this->spelt($this->outbound($subject));
    $sent = $this->spelt($edges);

    return array_values(array_filter(array_unique([...array_keys($held), ...array_keys($sent)]),
      fn (string $predicate): bool => ($held[$predicate] ?? []) !== ($sent[$predicate] ?? [])));
  }

  /**
   * @param list<Relation> $edges
   * @return array<string, list<string>> each predicate's edges, sorted
   */
  private function spelt(array $edges): array
  {
    $spelt = [];
    foreach ($edges as $edge) {
      $spelt[$edge->predicate][] = $this->json->encode([$edge->object->value, $edge->position,
        $edge->qualifiers->document()]);
    }

    return array_map(function (array $each): array {
      sort($each);

      return $each;
    }, $spelt);
  }

  /* The same target twice under one predicate is one edge sent twice, refused by
     name before the unique constraint answers it as a database error.
     @param array<string, true> $written */
  private function requireOnce(Relation $edge, array &$written): void
  {
    $key = $edge->predicate . ' → ' . $edge->object->value;
    if (isset($written[$key])) {
      throw MalformedValue::named('single edge to its target', $key);
    }
    $written[$key] = true;
  }

  private function insert(EntityId $subject, Relation $edge): void
  {
    if ($edge->subject->value !== $subject->value) {
      throw MalformedValue::named('edge of ' . $subject->value, $edge->subject->value);
    }
    $statement = $this->db->prepare('insert into relation'
      . ' (id, subject_id, predicate, object_id, position, props)'
      . ' select :id, :subject, :predicate, :object_id, :position, :props'
      . ' where exists (select 1 from entity where id = :object_id'
      . " and type = 'https://schema.org/' || :object_type"
      . " and (:object_term is null or json_extract(props, '$.term_code') = :object_term))");
    $qualifiers = $edge->qualifiers->document();
    $statement->execute([
      'id' => $this->uuid->generate(), 'subject' => $subject->value,
      'predicate' => $edge->predicate,
      'object_id' => $edge->object->value,
      'object_type' => $edge->objectType,
      'object_term' => $edge->objectTerm,
      'position' => $edge->position,
      'props' => $qualifiers === [] ? null : $this->json->encode($qualifiers),
    ]);
    if ($statement->rowCount() === 0) {
      throw EntityNotFound::withId($edge->object->value);
    }
  }

  private function requireRow(EntityId $id): void
  {
    $statement = $this->db->prepare('select 1 from entity where id = :id');
    $statement->execute(['id' => $id->value]);
    if ($statement->fetchColumn() === false) {
      throw EntityNotFound::withId($id->value);
    }
  }

  /** @return list<Relation> */
  private function select(string $where, EntityId $id): array
  {
    $statement = $this->db->prepare(self::SELECT . $where);
    $statement->execute(['id' => $id->value, 'predicates' => $this->json->encode($this->predicates->names)]);

    return array_map($this->relation(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
  }

  /** @param array<string, string|int|null> $row */
  private function relation(array $row): Relation
  {
    $type = substr((string) $row['object_type'], strlen('https://schema.org/'));

    return new Relation(
      $this->predicates,
      new EntityId((string) $row['subject_id']),
      (string) $row['predicate'],
      new EntityId((string) $row['object_id']),
      $type,
      $type === 'DefinedTerm' ? (string) $row['object_term'] : null,
      $row['position'] === null ? null : (int) $row['position'],
      new Props($row['props'] === null ? [] : $this->json->decode((string) $row['props'])),
    );
  }
}
