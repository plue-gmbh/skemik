<?php

declare(strict_types=1);

namespace Admin;

use Storage\Collection;

/* What every view's row works out alike for its record, built once for a page
   of them (`view/partials/list-row` and its four siblings): where the record
   opens, carrying the query that brings the reader back to this page, and its
   own locale for the spine chip's dots where the collection holds a work in
   several, since the table shows each row's own. The blank row + New record
   clones has neither. */
final readonly class Items
{
  /** @param array<string, string> $parameters the page's query */
  public function __construct(
    private Collection $collection,
    private array $parameters,
    private bool $multilingual,
  ) {}

  public function address(?SheetRow $row): ?string
  {
    return $row === null ? null : Rows::address($this->collection, $row->entity,
      http_build_query(array_diff_key($this->parameters, ['in' => true])));
  }

  /** @return ?list<Translation> */
  public function own(?SheetRow $row): ?array
  {
    return $this->multilingual && $row !== null ? [new Translation($row->entity->locale->tag, $row->entity)] : null;
  }
}
