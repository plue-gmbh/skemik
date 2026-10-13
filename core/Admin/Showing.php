<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\Relation;
use Domain\TranslationGroup;
use Profile\Profile;
use Storage\EntityRepository;
use Storage\RelationRepository;

/* The links that show a record, one rule for the library's count, Points here
   and the delete confirmation. The feed serves a link to a work as its translation
   in the reader's language (`Feed\Addresses::inReadersLocale`), so a link to the
   German screenshot shows the English one wherever its holder is read in English.
   A link naming the record counts always, being the one a purge removes, and a
   draft translation as a draft holder does, being on its way to be shown. The
   trash counts only what a purge removes, `RelationRepository::inbound`. */
final readonly class Showing
{
  public function __construct(
    private RelationRepository $relations,
    private EntityRepository $entities,
    private Profile $profile,
  ) {}

  /** @return list<Relation> */
  public function edges(Entity $record): array
  {
    if (!$this->profile->byType($record->type)->locale->forks()) {
      return $this->relations->inbound($record->id);
    }

    $edges = $this->relations->inboundToGroup(TranslationGroup::rootOf($record));

    return array_values(array_filter($edges, fn (Relation $edge): bool => $this->shows($edge, $record)));
  }

  /* A holder that forks is read in its own locale alone; every other type in each. */
  private function shows(Relation $edge, Entity $record): bool
  {
    if ($edge->object->value === $record->id->value) {
      return true;
    }
    $named = $this->entities->find($edge->object);
    $holder = $this->entities->find($edge->subject);
    $readIn = $this->profile->byType($holder->type)->locale->forks() ? $holder->locale->tag : null;

    return $named->locale->tag !== $record->locale->tag
      && ($readIn === null || $readIn === $record->locale->tag);
  }
}
