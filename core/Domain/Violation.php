<?php

declare(strict_types=1);

namespace Domain;

/* What failed, and where. A list of these is an answer; a bool is not. A
   finding about a stored row names the row, so a screen can link to it rather
   than parse a reason. The reason is closed (`Reason`) and its values are what
   its names stand for, so the feed spells it and a screen words it apart. A
   derived column an older version wrote is `Stale`, said apart from the rest
   since it is the code's doing and never the row's. */
final readonly class Violation
{
  /** @param array<string, string> $values what the reason's names stand for, `key` => `telephone` */
  public function __construct(
    public string $field,
    public Reason $reason,
    public array $values = [],
    public ?EntityId $record = null,
  ) {}

  /* A sentence only a developer or a key's script reads, kept as it was said. */
  public static function stated(string $field, string $sentence, ?EntityId $record = null): self
  {
    return new self($field, Reason::Stated, ['sentence' => $sentence], $record);
  }

  /* The feed's English. */
  public function said(): string
  {
    $names = array_map(fn (string $name): string => '{' . $name . '}', array_keys($this->values));

    return strtr($this->reason->value, array_combine($names, $this->values));
  }
}
