<?php

declare(strict_types=1);

namespace Profile;

/* One property of one type, as the seed declares it. The key is snake_case; the
   schema.org spelling is SchemaOrg's business, not this class's.
   Readonly: the marks are applied by building a new instance, never by setting.
   An option's word is `Seed\Terms`'s, per content language and never by the
   admin's: a house publishing `de-DE` with an English-only admin still gets its
   German status words. */
final readonly class FieldDefinition
{
  /**
   * @param list<string> $options controlled values, shipped with the profile — Select only
   * @param ?string $ref on Objects, the value object whose key set `Seed\ValueObjects` carries —
   *   which is not a type and is never resolved as one
   * @param list<string> $targets what an edge points at: every type the seed names
   *   for it, or a class above some of them (CreativeWork), ['DefinedTerm'] on a term, or ['*'] for any
   */
  public function __construct(
    public string $key,
    public string $label,
    public FieldKind $kind,
    public Cardinality $cardinality,
    public WrittenBy $writtenBy = WrittenBy::Authored,
    public bool $required = false,      // † — the Validator refuses to save without it
    public bool $starter = false,       // ‡ — in a new collection by default, never a rule
    public bool $repeatable = false,    // []
    public array $options = [],
    public ?string $ref = null,
    public ?Uniqueness $unique = null,  // ! — a duplicate is refused; nothing is generated
    public array $targets = [],
    public ?string $valueObject = null, // the VO this field was flattened out of
    public ?string $valueObjectKey = null,  // and which of its keys this is
    public ?string $valueObjectOf = null,   // and the property the object arrived on
    public ?string $notFlattened = null,    // or why it is still one text field
    public bool $writerFromHoldings = false,  // the one row the type cannot answer
    public bool $words = false,         // a Text or Objects field a reader reads — see holdsWords()
    public ?Derivation $derivation = null,  // ∂ — what computes it and what it reads
    public ?string $ofKind = null,      // the specialisation whose property this is, not the type's
    public bool $dialled = false,       // a number to dial — see dialled()
    public ?ClosedList $list = null,    // Select only: the world's list in place of options
    public ?string $set = null,         // T only: the set's code where it is not the property's name
    public ?string $block = null,       // the heading a flattened field stands under with its kin
    public bool $whole = false,         // a count — see whole()
    public array $keys = [],            // Objects only: the keys of a row this field carries — see keys()
  ) {
    /* Self-tests on the profile's own declarations. Each group is called inside
       `assert()`, so production neither checks nor calls it. */
    assert($this->marksAgree());
    assert($this->kindAgrees());
    assert($this->targetsAgree());
    assert($this->valueObjectAgrees());
  }

  /* The value-object declaration on a field the type writes itself: `currency` is
     MonetaryAmount's key and carried by both amounts, which `flatten` cannot express. */
  public function keyOf(string $valueObject, string $valueObjectKey, ?string $carriedBy = null): self
  {
    return $this->with(valueObject: $valueObject, valueObjectKey: $valueObjectKey, valueObjectOf: $carriedBy,
      block: $carriedBy === null ? null : explode(': ', $this->label)[0]);
  }

  /* Where a word file names the block: the property it arrived on and a colon,
     `billing_address:`, which no field's key can be. */
  public function blockKey(): ?string
  {
    return $this->valueObjectOf === null ? null
      : strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $this->valueObjectOf)) . ':';
  }

  /* What a select takes: its own options, or the codes of the list it names.
     @return list<string> */
  public function choices(): array
  {
    return $this->list?->codes() ?? $this->options;
  }

  /* A value object left as one text field, with the reason on the field.
     There is no silent exception, so a VO quietly staying text is a failure. */
  public function notFlattened(string $reason): self
  {
    return $this->with(notFlattened: $reason);
  }

  /* The keys of its value object a row of this field carries, where a use fills
     fewer than the object has: a register entry is a name and a value, and a
     unit and a range beside them are columns nobody fills. Every key where none
     is named, as a spec sheet fills them. */
  public function keys(string ...$keys): self
  {
    return $this->with(keys: $keys);
  }

  /** @return list<string> the keys a row holds, the object's own unless narrowed */
  public function carried(ValueObject $object): array
  {
    return $this->keys === [] ? $object->keys : $this->keys;
  }

  public function unique(?string $perEdge = null): self
  {
    return $this->with(unique: $perEdge === null
      ? Uniqueness::perType()
      : Uniqueness::perEdge($perEdge));
  }

  public function words(): self
  {
    return $this->with(words: true);
  }

  /* The words a reader reads, never an identifier, number, date or term: what
     `props.l10n` may carry on a type that localises. Rows marked so are
     translated whole, since an English button's link may be another page too. The search reads more
     (`FieldKind::isSearched`), since an identifier is looked for and never translated. */
  public function holdsWords(): bool
  {
    return $this->cardinality === Cardinality::S && ($this->words || $this->kind->isWords());
  }

  public function holdsMany(): bool
  {
    return $this->kind === FieldKind::Refs || $this->repeatable;
  }

  /* The whole test for a †: is there a sensible record without this field? If
     yes it is ‡ at most. A status with a starting value, an author or a date the
     spine keeps, a price a house quotes on request: each was † once, and each
     was a wall across ordinary work. */
  public function required(): self
  {
    return $this->with(required: true);
  }

  /* ‡ is what every house of the type adds on its first day; what only a
     website needs stays behind Fields. */
  public function starter(): self
  {
    return $this->with(starter: true);
  }

  public function repeatable(): self
  {
    return $this->with(repeatable: true);
  }

  public function derived(Derivation $how): self
  {
    return $this->with(writtenBy: WrittenBy::Derived, derivation: $how);
  }

  /* The one row whose writer is not the type's to fix: the reviews are rows
     here and the rating is computed over them, or they are at a platform and it
     is typed — only the holdings know, record by record, so the declaration
     stands either way. */
  public function derivable(Derivation $how): self
  {
    return $this->with(writerFromHoldings: true, derivation: $how);
  }

  /* A property the vocabulary gives a specialisation and not the type: held only
     by a record marked as that kind, and asked of that class by `Claims`. */
  public function ofKind(string $specialisation): self
  {
    return $this->with(ofKind: $specialisation);
  }

  /* One writing, `+4930123123`, which a link dials as it stands and a frontend
     spaces as its country does. Anything else is refused and never rewritten:
     *030 12 34 56* becomes a number only with a country nothing here knows. */
  public function dialled(): self
  {
    return $this->with(dialled: true);
  }

  /* A count, which the vocabulary types `Integer`: a fraction is refused. Still a
     number and no kind of its own, since it is drawn, sorted and filtered as one. */
  public function whole(): self
  {
    return $this->with(whole: true);
  }

  public function labelled(string $label, ?string $block = null): self
  {
    return $this->with(label: $label, block: $block ?? $this->block);
  }

  public function onFirstScreen(): bool
  {
    return $this->required || $this->starter;
  }

  private function marksAgree(): bool
  {
    assert(!($this->required && $this->writtenBy === WrittenBy::Derived),
      'a required derived field is a contradiction');
    /* Every † is implicitly ‡: a field that must be filled has to be on screen. */
    assert(!($this->required && $this->starter), 'a required field never also writes ‡');
    assert(!($this->unique !== null && $this->writtenBy === WrittenBy::Derived),
      'a unique derived field is a contradiction');
    assert(!$this->words || (in_array($this->kind, [FieldKind::Text, FieldKind::Objects], true)
      && $this->cardinality === Cardinality::S), 'only a Text or Objects field is marked as words');
    assert(!$this->dialled || ($this->kind === FieldKind::Text && !$this->words),
      'a number to dial is text nobody translates');
    assert(!$this->whole || $this->kind === FieldKind::Number, 'only a number is whole');
    /* The mark without the declaration is a field `Feed\JsonLd` has to know
       about by name, which puts the type table in a second place. */
    assert($this->writtenBy !== WrittenBy::Derived || $this->derivation !== null,
      'a derived field declares what computes it');
    assert($this->derivation === null
      || $this->writtenBy === WrittenBy::Derived || $this->writerFromHoldings,
      'nothing is computed for a field somebody types');
    assert($this->derivation?->function !== Derived::Range || $this->targets === ['Offer'],
      'an AggregateOffer is emitted under offers and nowhere else');
    assert($this->ofKind === null || !$this->onFirstScreen(),
      'a field only one kind of record holds is neither required nor on a new collection');

    return true;
  }

  private function kindAgrees(): bool
  {
    assert(($this->kind === FieldKind::Select) === (($this->options !== []) xor ($this->list !== null)),
      'a select has options or names a list, and nothing else has either');
    /* Objects names the VO whose keys the editor draws as columns; an edge names
       the types it may point at. Two questions, so two slots. */
    assert(($this->kind === FieldKind::Objects) === ($this->ref !== null),
      'a value-object ref belongs to objects and to nothing else');
    /* The kind exists for the repeatable case and means it. A seed that
       had to remember [] beside it could forget it. */
    assert($this->kind !== FieldKind::Objects || $this->repeatable,
      'an objects field is repeatable by what the kind means');
    assert($this->keys === [] || $this->kind === FieldKind::Objects, 'only rows carry a narrowed set of keys');
    assert(!$this->kind->isReference() || $this->cardinality->isEdge(), 'ref, refs and image are edges');

    return true;
  }

  private function targetsAgree(): bool
  {
    assert($this->cardinality->isEdge() === ($this->targets !== []),
      'every edge, and only an edge, names targets');
    assert($this->cardinality !== Cardinality::T || $this->targets === ['DefinedTerm'],
      'a term targets DefinedTerm');
    assert($this->set === null || $this->cardinality === Cardinality::T, 'only a term names its set');
    assert(!(in_array('*', $this->targets, true) && count($this->targets) > 1),
      "'*' is any target, never one among several");

    return true;
  }

  private function valueObjectAgrees(): bool
  {
    /* Declared together, never inferred from the field name —
       `contact_point_telephone` has two legal splits. */
    assert(($this->valueObject === null) === ($this->valueObjectKey === null),
      'a value object and the key it carries are declared together');
    /* Which property the object arrived on — `billing_address_locality` is
       `billingAddress` — so the emitter nests under the right property without
       reading the field's name. Null where one key serves every use of the object
       on this type: an invoice's currency is carried by both amounts. */
    assert($this->valueObjectOf === null || $this->valueObject !== null,
      'a carrying property belongs to a flattened field');
    assert(!($this->valueObject !== null && $this->notFlattened !== null),
      'a field is flattened or it says why it is not, never both');
    assert(($this->valueObjectOf === null) === ($this->block === null),
      'a field carried by a property stands under a heading, and only such a field');

    return true;
  }

  /* Through the constructor and never `clone`, so a copy's marks are asserted as the seed's are. */
  private function with(WrittenBy|Uniqueness|Derivation|array|string|bool|null ...$changed): self
  {
    return new self(...[...get_object_vars($this), ...$changed]);
  }
}
