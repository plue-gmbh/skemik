<?php

declare(strict_types=1);

namespace Storage;

use Domain\ActionType;
use Domain\EntityId;
use Domain\Props;

/* One `activity` row as Last changed and the log screen read it: its id, which
   a redaction names, the record or the account, key, language or collection
   acted on, what was done and what the log kept of it, when, and who, by the id
   of the person or the token, both null for the install, and the name kept with
   the row, null once redacted. */
final readonly class Activity
{
  public function __construct(
    public string $id,
    public EntityId|HouseObject $target,
    public ActionType $action,
    public ?Props $result,
    public string $at,
    public ?string $person,
    public ?string $token,
    public ?string $name,
  ) {}
}
