<?php

declare(strict_types=1);

namespace Admin;

use Storage\Collection;

/* What every view's row works out alike for its record, built once for a page
   of them (`view/partials/list-row` and its four siblings): where the record
   opens, carrying the query that brings the reader back to this page, and the
   locales for the spine chip's dots where the collection holds a work in
   several: every one of its group where the row stands for the work, each with
   where it opens, else its own. Each carries its marker among the install's
   locales, since a row's own tag alone cannot tell `en-US` from `en-GB`. A
   missing language opens its card only for whoever may make it. The blank row
   + New record clones has neither. */
final readonly class Items
{
  /* The record screen's Languages card, where a missing language is made or linked. */
  public const LANGUAGES = '#languages-title';

  /** @param array<string, string> $parameters the page's query
      @param array<string, string> $markers by locale, none where a row shows no language (`SpineDialog::markers`)
      @param array<string, list<Translation>> $groups by record, where each row stands for its work
      @param bool $makes whether the reader may make a missing language */
  public function __construct(
    private Collection $collection,
    private array $parameters,
    private array $markers,
    private array $groups = [],
    private bool $makes = false,
  ) {}

  public function address(?SheetRow $row): ?string
  {
    return $row === null ? null : Rows::address($this->collection, $row->entity, $this->query());
  }

  /** @return ?list<Translation> */
  public function own(?SheetRow $row): ?array
  {
    if ($this->markers === [] || $row === null) {
      return null;
    }
    $own = $row->entity->locale->tag;
    $group = $this->groups[$row->entity->id->value] ?? null;
    if ($group === null) {
      return [new Translation($own, $row->entity, marker: $this->markers[$own] ?? $own)];
    }

    return array_map(fn (Translation $each): Translation => new Translation($each->locale, $each->record,
      href: $this->opens($row, $each), marker: $this->markers[$each->locale]), $group);
  }

  private function opens(SheetRow $row, Translation $each): ?string
  {
    if ($each->record === null) {
      return $this->makes ? $this->address($row) . self::LANGUAGES : null;
    }

    return Rows::address($this->collection, $each->record, $this->query());
  }

  private function query(): string
  {
    return http_build_query(array_diff_key($this->parameters, ['in' => true]));
  }
}
