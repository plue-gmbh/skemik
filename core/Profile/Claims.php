<?php

declare(strict_types=1);

namespace Profile;

use Domain\Violation;

/* The checks that need no data: the profile against the released vocabulary,
   and against its own rules. They are asked before every release, which they
   block, and they are ordinary code rather than assertions, so they run whatever
   `zend.assertions` says. No install asks them: profile and snapshot ship in
   one signed archive, so the answer is the release's and no owner's to mend.
   A step outside the vocabulary is legal and declared: it is listed below with
   its reason, and one that is not listed is a defect. So is a listed one the
   vocabulary has since caught up with — a stale excuse is how a short list of
   honest exceptions becomes a place to put things. */
final readonly class Claims
{
  /* Names the profile declares although schema.org has no page for them. */
  private const OUTSIDE = [
    'broader' => 'SKOS: schema.org gives DefinedTerm no hierarchy, and a taxonomy without one is a flat list',
  ];

  /* Properties the vocabulary publishes, declared here on a class it does not
     list them on. All are the escape rung, and each costs the proof link. A
     flattened key is asked of the value object it was declared on, not of the
     type it landed on, so `currency` is MonetaryAmount's and in-domain. */
  private const OFF_DOMAIN = [
    'Organization.additionalProperty' => 'the profile\'s most common type has no other home for its own facts',
    'Brand.additionalProperty' => 'a brand\'s colours and typefaces have no other home in the vocabulary',
    'DefinedTerm.additionalProperty' => 'a term\'s own facts, an element\'s atomic mass, have no other home',
    'Action.additionalProperty' => 'a task\'s priority has no word in the vocabulary',
  ];

  /* Words the feed's own documents take where the vocabulary does not declare
     them, each on the field's entry, whose `PropertyValue` has no word for either:
     a private key would be the second vocabulary the word is there to avoid. */
  private const DOCUMENT_WORDS = [
    'PropertyValue.isAccessibleForFree' => 'whether a field leaves the house, as an arranging key reads it',
    'PropertyValue.inLanguage' => 'that a field holds words per language, in the profile',
  ];

  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private Vocabulary $vocabulary,
  ) {}

  /** @return list<Violation> empty is the only passing answer */
  public function check(): array
  {
    return array_merge(...array_values($this->checks()));
  }

  /** @return array<string, list<Violation>> by what was asked, so a gate can say which promise broke */
  public function checks(): array
  {
    return [
      'ceiling' => $this->checkCeiling(),
      'types' => $this->checkTypes(),
      'specialisations' => $this->checkSpecialisations(),
      'properties' => $this->checkProperties(),
      'overrides' => $this->checkOverrides(),
      'valueObjects' => $this->checkValueObjects(),
      'predicates' => $this->checkPredicates(),
      'words' => $this->checkWords(),
      ...(new Promises($this->profile))->checks(),
      ...(new Ranges($this->profile, $this->schema, $this->vocabulary))->checks(),
    ];
  }

  /* A type re-declares a pooled property to mark it required, to demote it to
     the first screen, or to narrow what the value is — all three are the owning
     type's to say. Who writes it is not: the pool has already answered, and a
     row reversing it leaves one property with two answers and `fields()` quietly
     taking the nearer one. `WebSite.inLanguage` was the one, authored beside a
     field the spine derives.
     @return list<Violation> */
  private function checkOverrides(): array
  {
    $violations = [];
    foreach ($this->profile->types() as $type) {
      $own = array_column($type->own, null, 'key');
      foreach ($type->inherited as $pooled) {
        $writer = ($own[$pooled->key] ?? $pooled)->writtenBy;
        if ($writer !== $pooled->writtenBy) {
          $where = $type->name . '.' . $pooled->key;
          $reason = sprintf('re-declared as %s where the block writes it %s',
            $writer->value, $pooled->writtenBy->value);
          $violations[] = new Violation($where, $reason);
        }
      }
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkCeiling(): array
  {
    $count = count($this->profile->types());

    return $count === Profile::CEILING
      ? []
      : [new Violation('profile', sprintf('%d types, and the ceiling is %d', $count, Profile::CEILING))];
  }

  /* Every type is a class the vocabulary publishes, so no name is one a
     validator cannot resolve.
     @return list<Violation> */
  private function checkTypes(): array
  {
    $violations = [];
    foreach ($this->profile->types() as $type) {
      if (!$this->vocabulary->hasType($type->name)) {
        $violations[] = new Violation($type->name, 'no such class in the released vocabulary');
      }
    }

    return $violations;
  }

  /* Every kind a type declares fields for is a class the vocabulary publishes
     and a descendant of that type — a specialisation is how the ceiling holds at
     thirty, so a token nobody can resolve turns *specialise instead of adding a
     table* into a string somebody typed.
     @return list<Violation> */
  private function checkSpecialisations(): array
  {
    $violations = [];
    foreach ($this->profile->types() as $type) {
      foreach (array_unique(array_filter(array_column($type->own, 'ofKind'))) as $name) {
        if (!$this->vocabulary->hasType($name)) {
          $violations[] = new Violation(
            $type->name . ' → ' . $name, 'no such class in the released vocabulary');
          continue;
        }
        if (!in_array($type->name, $this->vocabulary->ancestorsOf($name), true)) {
          $violations[] = new Violation(
            $type->name . ' → ' . $name, 'not a specialisation of this type');
        }
        if ($this->vocabulary->supersededBy($name) !== null) {
          $violations[] = new Violation(
            $type->name . ' → ' . $name, 'superseded by ' . $this->vocabulary->supersededBy($name));
        }
      }
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkProperties(): array
  {
    $violations = [];
    foreach ($this->profile->types() as $type) {
      foreach ($type->fields() as $field) {
        $name = $this->schema->propertyName($field->key) ?? $field->key;
        $violations = [...$violations, ...$this->checkProperty($field, $type, $name)];
      }
    }

    return $violations;
  }

  /* A flattened field is checked against the value object it declares,
     never against the type it sits on — `addressLocality` is PostalAddress's
     and the type never claimed it. Asking the type instead is what made the
     currency keys look like an off-domain step.
     @return list<Violation> */
  private function checkProperty(FieldDefinition $field, TypeDefinition $type, string $name): array
  {
    $domain = $this->schema->domainOf($field, $type->name);
    $where = $domain . '.' . $name;
    if (!$this->vocabulary->hasProperty($name)) {
      return isset(self::OUTSIDE[$name])
        ? []
        : [new Violation($where, 'not in the released vocabulary, and not declared as a step outside it')];
    }
    if (isset(self::OUTSIDE[$name])) {
      return [new Violation($where, 'declared outside the vocabulary, which now publishes it')];
    }
    if ($this->vocabulary->supersededBy($name) !== null) {
      return [new Violation($where, 'superseded by ' . $this->vocabulary->supersededBy($name))];
    }
    if (!$this->belongsToAnyEmitted($name, $domain)) {
      return isset(self::OFF_DOMAIN[$where])
        ? []
        : [new Violation($where, 'the vocabulary does not declare this property on this type')];
    }

    return isset(self::OFF_DOMAIN[$where])
      ? [new Violation($where, 'declared off-domain, and the vocabulary now lists it here')]
      : [];
  }

  /* One library for every asset, and the emitted @type follows from the MIME
     type, so a property the vocabulary declares on what this row will be
     emitted as is declared on this row: the derived @type made checkable. */
  private function belongsToAnyEmitted(string $property, string $type): bool
  {
    foreach ($this->schema->emittedAs($type) as $candidate) {
      if ($this->schema->belongsTo($property, $candidate)) {
        return true;
      }
    }

    return false;
  }

  /* Every flattened field names a value object `Seed\ValueObjects` declares and a key that
     value object has, and no value object quietly stays a text field. The second
     half is why the first is worth running — a mark and a reason are the only
     way a VO may remain unflattened, so a silent exception is a defect. Every key
     is asked of the value object's class, since no flattened field asks for a row's.
     @return list<Violation> */
  private function checkValueObjects(): array
  {
    $violations = [];
    foreach ($this->profile->types() as $type) {
      foreach ($type->fields() as $field) {
        $violations = [...$violations, ...$this->checkValueObject($field, $type->name . '.' . $field->key)];
      }
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkValueObject(FieldDefinition $field, string $where): array
  {
    $name = $field->valueObject ?? ($field->kind === FieldKind::Objects ? $field->ref : null);
    if ($name === null) {
      return [];
    }
    $object = $this->profile->valueObject($name);
    if ($object === null) {
      return [new Violation($where, sprintf('%s is no value object the profile declares', $name))];
    }
    $violations = [];
    if ($field->valueObjectKey !== null && !$object->has($field->valueObjectKey)) {
      $violations[] = new Violation($where, sprintf('%s has no key %s', $name, $field->valueObjectKey));
    }
    if ($field->required && !$object->canBeRequired()) {
      $violations[] = new Violation($where, sprintf('%s has no bearing key, so it cannot be required', $name));
    }
    foreach ($object->keys as $key) {
      $stale = match (true) {
        $this->vocabulary->supersededBy($key) !== null => 'superseded by ' . $this->vocabulary->supersededBy($key),
        !$this->schema->belongsTo($key, $name) => 'not declared on ' . $name . ' in the vocabulary',
        default => null,
      };
      $violations = $stale === null ? $violations : [...$violations, new Violation($where, "$name.$key $stale")];
    }

    return $violations;
  }

  /* Every predicate is a schema.org property name verbatim, save `broader`,
     which is SKOS and named as the exception — which is what makes
     "the edges add no vocabulary" a checkable sentence rather than a claim.
     @return list<Violation> */
  private function checkPredicates(): array
  {
    $violations = [];
    foreach ((new Predicate($this->profile, $this->schema))->names as $name) {
      if (!$this->vocabulary->hasProperty($name) && !isset(self::OUTSIDE[$name])) {
        $violations[] = new Violation($name, 'a predicate that is no property of the vocabulary');
      }
    }

    return $violations;
  }

  /* A document word is still one the vocabulary publishes and still off its
     domain there: one the vocabulary has since placed is a stale excuse.
     @return list<Violation> */
  private function checkWords(): array
  {
    $violations = [];
    foreach (array_keys(self::DOCUMENT_WORDS) as $where) {
      [$type, $name] = explode('.', $where);
      $stale = match (true) {
        !$this->vocabulary->hasProperty($name) => 'not in the released vocabulary',
        $this->vocabulary->supersededBy($name) !== null
          => 'superseded by ' . $this->vocabulary->supersededBy($name),
        $this->schema->belongsTo($name, $type) => 'declared off-domain, and the vocabulary now lists it here',
        default => null,
      };
      $violations = $stale === null ? $violations : [...$violations, new Violation($where, $stale)];
    }

    return $violations;
  }
}
