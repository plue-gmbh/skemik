<?php

declare(strict_types=1);

namespace Profile;

use Domain\Exceptions\UnknownPredicate;

/* The legal edge predicates. Not typed here: a predicate is legal exactly when
   a type table declares the field `E` or `T`, plus the two the spine emits — so
   the set is built from the type definitions and cannot drift from them the way
   a hand-kept list did. */
final readonly class Predicate
{
  /* Emitted by Feed\JsonLd from the spine's translation_of: no type owns them,
     so no type declares them. */
  public const SPINE = ['translationOfWork', 'workTranslation'];

  /** @var list<string> */
  public array $names;

  public function __construct(Profile $profile, SchemaOrg $schema)
  {
    $names = array_fill_keys(self::SPINE, true);
    foreach ($profile->types() as $type) {
      foreach ($type->edges() as $field) {
        $names[$schema->propertyName($field->key) ?? throw UnknownPredicate::named($field->key)] = true;
      }
    }
    ksort($names);
    $this->names = array_keys($names);

    /* The set is the types' own, so what is worth asserting is that nothing else
       got in: a predicate is a schema.org property name verbatim. */
    assert($this->names !== self::SPINE, 'no type declares an edge');
    assert($this->allCamelCase(), 'a predicate is not a property name');
  }

  public function has(string $predicate): bool
  {
    return in_array($predicate, $this->names, true);
  }

  /* Rejected at construction of the relation, never at save. */
  public function require(string $predicate): void
  {
    if (!$this->has($predicate)) {
      throw UnknownPredicate::named($predicate);
    }
  }

  private function allCamelCase(): bool
  {
    foreach ($this->names as $name) {
      if (preg_match('/^[a-z][A-Za-z0-9]*$/', $name) !== 1) {
        return false;
      }
    }

    return true;
  }
}
