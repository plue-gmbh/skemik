<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\Cardinality;
use Profile\ClosedList;
use Profile\FieldDefinition;
use Profile\FieldKind;

/* The shorthand the type tables are written in: a kind and a cardinality per
   row. The marks (†, ‡, [], ∂) are applied on the field itself. */
final readonly class Fields
{
  public function text(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Text);
  }

  public function longText(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::LongText);
  }

  public function markdown(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Markdown);
  }

  public function date(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Date);
  }

  public function dateTime(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::DateTime);
  }

  public function duration(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Duration);
  }

  public function number(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Number);
  }

  public function boolean(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Boolean);
  }

  public function link(string $key, string $label): FieldDefinition
  {
    return $this->scalar($key, $label, FieldKind::Link);
  }

  /* An image, video or file field — the edge target is the asset, and what
     lands in the JSON-LD is the asset's own @type, not the field's. */
  public function image(string $key, string $label): FieldDefinition
  {
    return new FieldDefinition($key, $label, FieldKind::Image, Cardinality::E, targets: ['MediaObject']);
  }

  /* Every target the type names, in its order — `author` is a Person or an
     Organization, and a club that wrote its own article is the second. */
  public function ref(string $key, string $label, string ...$targets): FieldDefinition
  {
    return new FieldDefinition($key, $label, FieldKind::Ref, Cardinality::E, targets: $targets);
  }

  public function refs(string $key, string $label, string ...$targets): FieldDefinition
  {
    return new FieldDefinition($key, $label, FieldKind::Refs, Cardinality::E, targets: $targets);
  }

  /* A repeatable value object, the `objects` kind. `S`, so it lives in
     entity.props and is no predicate: a VO is fields, and a reference
     inside one stays a value. The ref names the VO whose key set `ValueObjects` carries,
     and the editor reads it to draw the columns — which are again the other kinds.
     A single-valued VO never reaches here; it resolves into fields. */
  public function objects(string $key, string $label, string $valueObject): FieldDefinition
  {
    return new FieldDefinition(
      $key, $label, FieldKind::Objects, Cardinality::S, repeatable: true, ref: $valueObject,
    );
  }

  /* One key of a flattened value object. The value object and its key
     are declared here, never inferred from the field name: SchemaOrg asks this
     declaration before it spells anything, so `address_locality` resolves to
     PostalAddress.addressLocality rather than to an `addressStreet` the
     vocabulary has never published. What it holds is the caller's, because a
     key is an ordinary field of the other kinds — a postcode is text, a country
     the world's list, a return method the options of a select. Its block is
     named by the English before the colon, `Address` of `Address: Street`, once
     here, so a label said whole elsewhere leaves the block its name.
     @param FieldKind|ClosedList|list<string> $holds a kind, the world's list or the options */
  public function valueOf(
    string $valueObject,
    string $valueObjectKey,
    string $key,
    string $label,
    FieldKind|ClosedList|array $holds = FieldKind::Text,
    ?string $valueObjectOf = null,
  ): FieldDefinition {
    return new FieldDefinition(
      $key, $label, $holds instanceof FieldKind ? $holds : FieldKind::Select, Cardinality::S,
      options: is_array($holds) ? $holds : [],
      valueObject: $valueObject,
      valueObjectKey: $valueObjectKey,
      valueObjectOf: $valueObjectOf,
      list: $holds instanceof ClosedList ? $holds : null,
      block: $valueObjectOf === null ? null : explode(': ', $label)[0],
    );
  }

  /* A controlled term: an edge whose target is a DefinedTerm of the field's set,
     which is the property's name unless fields spelt apart mean one set — the
     regions an offer allows and a job asks for are one list of regions. */
  public function term(string $key, string $label, ?string $set = null): FieldDefinition
  {
    return new FieldDefinition($key, $label, FieldKind::Ref, Cardinality::T, targets: ['DefinedTerm'],
      set: $set);
  }

  /* A closed list of tokens, stored as the token: schema.org's enumeration
     members, which mean the same in every installation, and the few closed sets
     it spells as text. Not a term and never a row, since nothing about
     `EventCancelled` is a house's to own; its words ship in `language/`. */
  public function select(string $key, string $label, array $options): FieldDefinition
  {
    return new FieldDefinition($key, $label, FieldKind::Select, Cardinality::S, options: $options);
  }

  /* A select on what the world closes — a currency, a country, a language —
     whose choices are the list's codes and never the profile's options. */
  public function listed(string $key, string $label, ClosedList $list): FieldDefinition
  {
    return new FieldDefinition($key, $label, FieldKind::Select, Cardinality::S, list: $list);
  }

  private function scalar(string $key, string $label, FieldKind $kind): FieldDefinition
  {
    return new FieldDefinition($key, $label, $kind, Cardinality::S);
  }
}
