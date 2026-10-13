<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;
use Support\Uuid;

/* A record's id, a type of its own so an account's or a key's id cannot be
   passed where a record's is meant. */
final readonly class EntityId
{
  public function __construct(public string $value)
  {
    if (preg_match(Uuid::SHAPE, $value) !== 1) {
      throw MalformedValue::named('record id', $value);
    }
  }
}
