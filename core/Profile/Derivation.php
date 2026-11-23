<?php

declare(strict_types=1);

namespace Profile;

/* A `∂` as a declaration and not only a mark: what computes the value and what
   it reads, or `Feed\JsonLd` would carry one `match` arm per derived field, the
   type's table standing in a second place. The chain is bounded here and never
   at the request, so the tree of declarations is finite and reviewable. There is
   no `where`, selection living at the address, but for two settled cases, each
   over a type with no public state, which the three public gates would answer
   nothing of: `lapsesAt`, a count passing over a cancelled reservation, which
   reads a lifecycle the way a draft is not published, and `onlyAt`, a sum of
   the observations of one variable, since page views and members added up
   are no figure. Either reads every row in force, drafts among them. `from`
   names the type counted where an edge is not its own: a comment and an
   article are both `about` a record. */
final readonly class Derivation
{
  /** @param list<string> $path the edge hops, then the target key read, or
   *   nothing where the spine answers */
  private function __construct(
    public Derived $function,
    public array $path = [],
    public bool $inbound = false,
    public ?string $minus = null,
    public ?string $gateField = null,
    public ?string $gateValue = null,
    public bool $gateAdmits = false,  /* whether the value is the one admitted or the one passed over */
    public ?string $from = null,
  ) {
    assert($this->pathAgrees());
    assert(($this->gateField === null) === ($this->gateValue === null),
      'a gate and the token it reads are declared together');
    assert(!$this->inbound || $this->function->readsEdge(), 'only an edge has a direction');
    assert($this->minus === null || $this->function === Derived::Count,
      'only a count is taken from a number beside it');
    assert($this->from === null || $this->inbound, 'only the rows naming a record are of a type to name');
  }

  /* The spine's own columns. `Feed\JsonLd` emits `inLanguage` from the spine for
     every record of a forking type, so this declares the field rather than a
     second emitter — the type's table holds every derived field or it holds
     none of them. */
  public static function spine(): self
  {
    return new self(Derived::Locale);
  }

  public static function over(Derived $function, string ...$path): self
  {
    return new self($function, $path);
  }

  /* An edge the counted row carries, not this one: a reservation names the
     event, and no event names its reservations. */
  public function inbound(): self
  {
    return $this->with(inbound: true);
  }

  /* An event's places left is a capacity minus a count, and the capacity is the only
     authored number any derived field reads. */
  public function minus(string $field): self
  {
    return $this->with(minus: $field);
  }

  public function lapsesAt(string $field, string $value): self
  {
    return $this->with(gateField: $field, gateValue: $value);
  }

  public function onlyAt(string $field, string $value): self
  {
    return $this->with(gateField: $field, gateValue: $value, gateAdmits: true);
  }

  public function from(string $type): self
  {
    return $this->with(from: $type);
  }

  /** The target key the function reads, where the path names one. */
  public function reads(): ?string
  {
    return count($this->path) > 1 ? $this->path[count($this->path) - 1] : null;
  }

  private function pathAgrees(): bool
  {
    assert(($this->function === Derived::Locale) === ($this->path === []),
      'the spine reads its own column and every other derivation names a source');
    assert(!$this->function->readsEdge() || in_array($this->function, [Derived::Count, Derived::Range], true)
      || $this->reads() !== null, 'only a count and a range need no key: the targets are what they read');

    return true;
  }

  private function with(
    ?bool $inbound = null,
    ?string $minus = null,
    ?string $gateField = null,
    ?string $gateValue = null,
    ?bool $gateAdmits = null,
    ?string $from = null,
  ): self {
    return new self(
      $this->function, $this->path,
      $inbound ?? $this->inbound,
      $minus ?? $this->minus,
      $gateField ?? $this->gateField,
      $gateValue ?? $this->gateValue,
      $gateAdmits ?? $this->gateAdmits,
      $from ?? $this->from,
    );
  }
}
