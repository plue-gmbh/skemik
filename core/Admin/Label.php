<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Profile\TypeDefinition;

/* A record's label: its type's naming field (`TypeDefinition::namedBy`) in the
   record's own locale, falling back to its slug and never to an id. The sidebar,
   the chips, the pickers, the back links and the log's targets all print this
   one derivation. */
final readonly class Label
{
  public function of(Entity $entity, TypeDefinition $type): string
  {
    $value = $entity->props->document()[$type->namedBy()] ?? null;

    return is_string($value) && trim($value) !== '' ? $value : $entity->slug->value;
  }
}
