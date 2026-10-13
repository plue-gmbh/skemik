<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;

/* Typed access to the JSON document, so no `mixed` escapes into the rest of the
   codebase: a key holding another kind than asked for throws, and whether its
   content is valid is `Profile\Validator`'s answer, not this class's.
   Unknown keys are preserved untouched on write — schema.org is open, and a field
   this version does not know must survive a round trip. Setting null removes the
   key, since the Validator reads null and absent as the same thing. */
final readonly class Props
{
  /** @param array<string, mixed> $document */
  public function __construct(private array $document)
  {
    if ($document !== [] && array_is_list($document)) {
      throw MalformedValue::notA('a JSON object', 'props');
    }
  }

  public function has(string $key): bool
  {
    return isset($this->document[$key]);
  }

  public function string(string $key): ?string
  {
    $value = $this->document[$key] ?? null;
    if (!($value === null || is_string($value))) {
      throw MalformedValue::notA('a string', $key);
    }

    return $value;
  }

  public function int(string $key): ?int
  {
    $value = $this->document[$key] ?? null;
    if (!($value === null || is_int($value))) {
      throw MalformedValue::notA('an integer', $key);
    }

    return $value;
  }

  public function bool(string $key): ?bool
  {
    $value = $this->document[$key] ?? null;
    if (!($value === null || is_bool($value))) {
      throw MalformedValue::notA('a boolean', $key);
    }

    return $value;
  }

  /** @return list<string|int|float|bool|array<array-key, mixed>> */
  public function list(string $key): array
  {
    $value = $this->document[$key] ?? [];
    if (!(is_array($value) && array_is_list($value))) {
      throw MalformedValue::notA('a list', $key);
    }

    return $value;
  }

  /** @param string|int|float|bool|array<array-key, mixed>|null $value */
  public function with(string $key, string|int|float|bool|array|null $value): self
  {
    $next = $this->document;
    $next[$key] = $value;
    if ($value === null) {
      unset($next[$key]);
    }
    assert(array_diff_key($this->document, [$key => true], $next) === [], 'a key was lost');

    return new self($next);
  }

  /** @return array<string, mixed> as stored, for Storage and the Validator */
  public function document(): array
  {
    return $this->document;
  }
}
