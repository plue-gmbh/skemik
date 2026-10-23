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
   the row, null once redacted. A save and an arrangement keep only the keys
   they changed, so a run of them by one hand folds into one line. */
final readonly class Activity
{
  private const FOLDED = ['fields', 'spine', 'let_out', 'withheld', 'added', 'removed'];

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

  /* This row with an older one on the same thing by the same hand folded in,
     their keys together; null where either is anything else, or where one
     field stands under two keys, added then published for one, since that is
     two actions and would read as one. */
  public function joined(self $older): ?self
  {
    if (!$this->folds() || !$older->folds() || $this->target != $older->target
      || [$this->person, $this->token] !== [$older->person, $older->token] || $this->crosses($older)) {
      return null;
    }
    $kept = $this->result?->document() ?? [];
    foreach (self::FOLDED as $key) {
      $both = array_values(array_unique([...$this->listed($key), ...$older->listed($key)]));
      $kept = $both === [] ? $kept : [...$kept, $key => $both];
    }

    return clone($this, ['result' => new Props($kept)]);
  }

  private function folds(): bool
  {
    $keys = array_diff(array_keys($this->result?->document() ?? []), ['about']);

    return $this->action === ActionType::Update && $keys !== [] && array_diff($keys, self::FOLDED) === [];
  }

  private function crosses(self $older): bool
  {
    return array_any(self::FOLDED, fn (string $mine): bool => array_any(self::FOLDED, fn (string $theirs): bool
      => $mine !== $theirs && array_intersect($this->listed($mine), $older->listed($theirs)) !== []));
  }

  /** @return list<string|int|float|bool|array<array-key, mixed>> */
  private function listed(string $key): array
  {
    return $this->result?->list($key) ?? [];
  }
}
