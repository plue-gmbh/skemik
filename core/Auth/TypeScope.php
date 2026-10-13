<?php

declare(strict_types=1);

namespace Auth;

use Domain\Exceptions\MalformedValue;

/* One class used twice, for `account.write_scope` and `account.read_scope`: the
   fact differs, the shape does not, and a second class would be where the two
   drift apart. Keys are type names, since the scope is the thirty types and the
   thirty checkboxes are those.
   An empty scope is refused: an editor who may write nothing is a `viewer`, and
   an account that reads nothing as staff should be a token or a visitor. */
final readonly class TypeScope
{
  private const TYPE_NAME = '/^[A-Z][A-Za-z]+$/';

  /** @param ?list<string> $types sorted and distinct, null for every type */
  private function __construct(public ?array $types) {}

  public static function all(): self
  {
    return new self(null);
  }

  /** @param list<string> $types */
  public static function only(array $types): self
  {
    $types = array_values(array_unique($types));
    sort($types);
    foreach ($types as $type) {
      if (preg_match(self::TYPE_NAME, $type) !== 1) {
        throw MalformedValue::named('type name in a scope', $type);
      }
    }

    return $types === [] ? throw MalformedValue::named('scope', 'no type') : new self($types);
  }

  /* The column: space-separated names, null for every type. */
  public static function parse(?string $stored): self
  {
    return $stored === null ? self::all() : self::only(explode(' ', $stored));
  }

  public function stored(): ?string
  {
    return $this->types === null ? null : implode(' ', $this->types);
  }

  public function allows(string $type): bool
  {
    return $this->types === null || in_array($type, $this->types, true);
  }

  public function covers(self $other): bool
  {
    if ($this->types === null) {
      return true;
    }

    return $other->types !== null && array_diff($other->types, $this->types) === [];
  }
}
