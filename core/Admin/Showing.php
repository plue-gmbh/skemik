<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\Reason;
use Domain\Relation;
use Domain\TranslationGroup;
use Domain\Violation;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Storage\EntityRepository;
use Storage\RelationRepository;

/* The links that show a record, one rule for the library's count, Points here
   and the delete confirmation. The feed serves a link to a work as its translation
   in the reader's language (`Feed\Addresses::inReadersLocale`), so a link to the
   German screenshot shows the English one wherever its holder is read in English.
   A link naming the record counts always, being the one a purge removes, and a
   draft translation as a draft holder does, being on its way to be shown. The
   trash counts only what a purge removes, `RelationRepository::inbound`. A file
   stays of the kind the fields showing it take, replaced (`Intake::file`) or
   saved with its format typed, by hand or through a key. */
final readonly class Showing
{
  public function __construct(
    private RelationRepository $relations,
    private EntityRepository $entities,
    private Profile $profile,
    private SchemaOrg $schema,
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

  /* The kinds of file every field showing the record takes (`SchemaOrg::fileKinds`),
     empty where any file may stand.
     @return list<string> */
  public function fileKinds(Entity $record): array
  {
    $kinds = null;
    foreach ($this->edges($record) as $edge) {
      $field = array_find($this->profile->byType($this->entities->find($edge->subject)->type)->edges(),
        fn (FieldDefinition $field): bool => $this->schema->propertyName($field->key) === $edge->predicate);
      $taken = $field === null ? [] : $this->schema->fileKinds($field->targets);
      $kinds = match (true) {
        $taken === [] => $kinds,
        $kinds === null => $taken,
        default => array_values(array_intersect($kinds, $taken)),
      };
    }

    return $kinds ?? [];
  }

  /* An asset saved with a format no field showing it takes is refused, as the file
     itself would be, or a picture an event shows turns into a PDF by one typed line.
     @return list<Violation> */
  public function kept(Entity $asset): array
  {
    $kinds = $asset->typeName() === 'MediaObject' ? $this->fileKinds($asset) : [];
    $kind = (string) strtok((string) $asset->props->string('encoding_format'), '/');

    return $kinds === [] || in_array($kind, $kinds, true) ? []
      : [new Violation('encoding_format', Reason::NotShown, ['kinds' => implode('/ or ', $kinds)])];
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
