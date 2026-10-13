<?php

declare(strict_types=1);

namespace Profile;

/* Where a value lives. It answers what a value is and
   nothing else — WrittenBy is the other axis and the two are independent. */
enum Cardinality: string
{
  case S = 'S';   // scalar or value object — entity.props
  case E = 'E';   // edge to another entity — relation
  case T = 'T';   // controlled term — relation, pointing at a DefinedTerm

  /* E and T are both edges in the relation table, so both are legal predicates:
     a T field is an edge whose target is a term. */
  public function isEdge(): bool
  {
    return $this !== self::S;
  }
}
