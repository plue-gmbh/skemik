<?php

declare(strict_types=1);

namespace Profile;

/* schema.org's released vocabulary, whole: each class with its parents, each
   property with the types it is declared on. A snapshot, committed beside the
   model and refreshed deliberately — never fetched, because a check that needs
   the network is a check that gets skipped.
   Whole and not pruned to what the profile names: a snapshot holding only the
   properties already declared here cannot answer the question `Claims` asks of
   a newly added one, and reported it as a name schema.org does not publish. */
final readonly class Vocabulary
{
  /* Read by whoever composes the checks; this class does no I/O itself. */
  public const FILE = __DIR__ . '/vocabulary.json';

  /** @var array<string, list<string>> */
  private array $classes;

  /** @var array<string, list<string>> */
  private array $properties;

  /** @var array<string, string> names the vocabulary has superseded, each with its successor */
  private array $superseded;

  /** @param array<string, mixed> $snapshot the decoded vocabulary.json */
  public function __construct(array $snapshot)
  {
    $this->classes = $snapshot['classes'] ?? [];
    $this->properties = $snapshot['properties'] ?? [];
    $this->superseded = $snapshot['superseded'] ?? [];

    assert($this->classes !== [] && $this->properties !== [], 'the vocabulary snapshot is empty');
  }

  public function hasType(string $type): bool
  {
    return isset($this->classes[$type]);
  }

  public function hasProperty(string $property): bool
  {
    return isset($this->properties[$property]);
  }

  /* Null too for a snapshot distilled before it kept them. */
  public function supersededBy(string $name): ?string
  {
    return $this->superseded[$name] ?? null;
  }

  /** @return ?list<string> the types the property is declared on, or null if unknown here */
  public function domainsOf(string $property): ?array
  {
    return $this->properties[$property] ?? null;
  }

  /** @return list<string> every class filed under the type, by name */
  public function descendantsOf(string $type): array
  {
    $filed = array_filter(array_keys($this->classes),
      fn (string $class): bool => in_array($type, $this->ancestorsOf($class), true));
    sort($filed);

    return $filed;
  }

  /** @return list<string> every ancestor, nearest first */
  public function ancestorsOf(string $type): array
  {
    $seen = [];
    $frontier = $this->classes[$type] ?? [];
    while ($frontier !== []) {
      $next = array_shift($frontier);
      if (in_array($next, $seen, true)) {
        continue;
      }
      $seen[] = $next;
      $frontier = [...$frontier, ...$this->classes[$next] ?? []];
    }

    return $seen;
  }
}
