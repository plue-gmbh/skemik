<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Relation;
use Domain\TranslationGroup;
use Http\Request;
use Profile\FieldDefinition;
use Profile\SchemaOrg;
use Storage\EntityRepository;
use Storage\RelationRepository;

/* The targets a field holds at the places its picker still lists, which the
   search does not offer again: a picker names a held target by its place and
   never by its id (`Chip::held`), so what it holds is read here. Each is its
   group's root, since a hit stands for its group. */
final readonly class HeldTargets
{
  public function __construct(
    private EntityRepository $entities,
    private RelationRepository $relations,
    private SchemaOrg $schema,
    private Authorizer $authorizer,
  ) {}

  /** Only for a staff reader of the record's type, who reads every field of it and
      is the only one with a picker, writing being within reading: for anybody else
      the hits missing would tell of an edge the feed withholds.
      @return list<string> */
  public function roots(Account $account, Request $request, ?FieldDefinition $field): array
  {
    $record = $request->query('record');
    $places = (string) $request->query('held');
    if ($field === null || $record === null || $places === '') {
      return [];
    }
    $subject = $this->entities->find(new EntityId($record));
    if ($this->authorizer->readsAsPublic($account, $subject->type)) {
      return [];
    }
    $predicate = (string) $this->schema->propertyName($field->key);
    $mine = array_values(array_filter($this->relations->outbound($subject->id),
      fn (Relation $edge): bool => $edge->predicate === $predicate));
    $edges = array_filter(array_map(fn (string $place): ?Relation => $mine[(int) $place] ?? null,
      explode(',', $places)));

    return array_values(array_filter(array_map($this->rootOf(...), $edges)));
  }

  /* A target that is gone is offered by nobody, so it excludes nothing. */
  private function rootOf(Relation $edge): ?string
  {
    try {
      return TranslationGroup::rootOf($this->entities->find($edge->object))->value;
    } catch (EntityNotFound) {
      return null;
    }
  }
}
