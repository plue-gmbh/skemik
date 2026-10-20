<?php

declare(strict_types=1);

namespace Profile;

use Profile\Seed\Media;

/* The proof layer: every type and every field points at its schema.org entry.
   It returns null rather than guessing — a dead link is worse than no link, and
   a field the profile places off-domain on purpose (the brand tokens) shows
   the gap rather than hiding it. */
final readonly class SchemaOrg
{
  private const BASE = 'https://schema.org/';

  /* Keys whose schema.org spelling is not the camelCase of the key. */
  private const ALIAS = [
    'tax_id' => 'taxID',
    'vat_id' => 'vatID',
    'product_id' => 'productID',
    'in_product_group_with_id' => 'inProductGroupWithID',
    'product_group_id' => 'productGroupID',
    'checkout_page_url_template' => 'checkoutPageURLTemplate',
  ];

  /** @var array<string, FieldDefinition> every field the profile declares, by key */
  private array $fields;

  /** @var list<string> the value objects that are one number and its unit */
  private array $amounts;

  public function __construct(Profile $profile, private Vocabulary $vocabulary)
  {
    $fields = [];
    foreach ($profile->types() as $type) {
      foreach ($type->fields() as $field) {
        $fields[$field->key] ??= $field;
      }
    }
    $this->fields = $fields;
    $this->amounts = array_map(fn (ValueObject $object): string => $object->name,
      array_filter($profile->valueObjects(), fn (ValueObject $object): bool => $object->amount));
    assert($this->amountsApart($profile), 'two amounts of one type answer to one name at the address');
    assert($this->targetsAreHeld($profile), 'an edge points at a class none of the types is');
  }

  /* No link where the vocabulary has no page: a field of a collection somebody
     built themselves, and the one the profile declares outside schema.org on
     purpose, `broader`. */
  public function propertyUrl(string $fieldKey): ?string
  {
    $name = $this->propertyName($fieldKey);

    return $name !== null && $this->vocabulary->hasProperty($name) ? self::BASE . $name : null;
  }

  /* The property a flattened field's object arrives on, `acceptedAnswer` for the
     answer's `upvoteCount`: without it the key's own page reads as the type's. */
  public function objectUrl(FieldDefinition $field): ?string
  {
    $name = $field->valueObjectOf;

    return $name !== null && $this->vocabulary->hasProperty($name) ? self::BASE . $name : null;
  }

  /* Which class the vocabulary is asked about for this field: the value object
     it was flattened out of, the specialisation whose property it is, and the
     type otherwise. */
  public function domainOf(FieldDefinition $field, string $type): string
  {
    return $field->valueObject ?? $field->ofKind ?? $type;
  }

  /* The spelling, whether or not the vocabulary carries it — the predicate set
     needs a name for every edge, including the two that are SKOS. */
  public function propertyName(string $fieldKey): ?string
  {
    $field = $this->fields[$fieldKey] ?? null;
    if ($field === null) {
      return null;
    }

    /* A flattened field carries the value object's key, which is what
       schema.org publishes — camelCasing `address_street` would ask the
       vocabulary for an `addressStreet` it does not have. */
    if ($field->valueObjectKey !== null) {
      return $field->valueObjectKey;
    }

    return self::ALIAS[$fieldKey]
      ?? preg_replace_callback('/_([a-z0-9])/', fn (array $m): string => strtoupper($m[1]), $fieldKey);
  }

  /* A field as a document spells it, a value object's key by the path to it:
     what a refusal names, since the caller wrote that and never the stored key. */
  public function spelt(TypeDefinition $type, string $fieldKey): string
  {
    $field = $type->field($fieldKey);
    $name = $field === null ? null : $this->propertyName($fieldKey);
    if ($field === null || $name === null) {
      return $fieldKey;
    }

    return $field->valueObjectOf === null ? $name : $field->valueObjectOf . '.' . $name;
  }

  /* The code of the set a term field draws on: the property's name, so every
     `keywords` field of the house shares one list, unless the seed names one. */
  public function termSet(FieldDefinition $field): string
  {
    return $field->set ?? (string) $this->propertyName($field->key);
  }

  /* What the address calls a field: its spelling, but for an amount's number,
     which answers to the property the amount arrives on. That is the name the
     document shows, where `value` would name five fields of an offer at once. */
  public function parameterName(string $fieldKey): ?string
  {
    $field = $this->fields[$fieldKey] ?? null;

    return $field !== null && $this->isAmountNumber($field) ? $field->valueObjectOf
      : $this->propertyName($fieldKey);
  }

  private function isAmountNumber(FieldDefinition $field): bool
  {
    return $field->valueObjectOf !== null && $field->kind === FieldKind::Number
      && in_array($field->valueObject, $this->amounts, true);
  }

  private function amountsApart(Profile $profile): bool
  {
    foreach ($profile->types() as $type) {
      $names = array_map(fn (FieldDefinition $field): ?string => $field->valueObjectOf,
        array_filter($type->fields(), $this->isAmountNumber(...)));
      if (count($names) !== count(array_unique($names))) {
        return false;
      }
    }

    return true;
  }

  /* A target is one of the types or a class above some of them: `citation` means
     a CreativeWork, which is none of the thirty and what an article and a photo are. */
  private function targetsAreHeld(Profile $profile): bool
  {
    $names = array_map(fn (TypeDefinition $type): string => $type->name, $profile->types());
    $pointing = array_merge(...array_map(fn (TypeDefinition $type): array => $type->edges(), $profile->types()));
    foreach ([...$pointing, ...$profile->valueObjects()] as $each) {
      $unheld = array_filter($each->targets, fn (string $target): bool => array_filter($names,
        fn (string $name): bool => $this->takes([$target], $name)) === []);
      if ($unheld !== []) {
        return false;
      }
    }

    return true;
  }

  /* What a plain value is as schema.org names it, stated by the profile and asked
     by `Ranges`: a count is an `Integer`, a token of a closed list the
     enumeration it is a member of, `EventStatusType`, or text where it is none.
     @param list<string> $options @return list<string> */
  public function holds(FieldKind $kind, array $options = [], bool $whole = false): array
  {
    if ($options === []) {
      return [$whole ? 'Integer' : $kind->datatype()];
    }

    return array_values(array_unique(array_map(fn (string $option): string
      => $this->vocabulary->enumerationsOf($option)[0] ?? 'Text', $options)));
  }

  /* A fixed list's value as the JSON-LD document spells it: the member's address,
     `https://schema.org/EventScheduled`, where the vocabulary types the token as a
     member of an enumeration, and null for a token of the house's own. */
  public function member(string $token): ?string
  {
    return $this->vocabulary->enumerationsOf($token) === [] ? null : self::BASE . $token;
  }

  /* An edge to a class takes what the vocabulary says is one: a LocalBusiness is
     an Organization and a Place, so a branch sells an order and hosts an event. */
  public function accepts(FieldDefinition $field, string $type): bool
  {
    return $this->takes($field->targets, $type);
  }

  /* A record of the type may stand behind the targets: the library's, whose
     file decides what it leaves as, behind any class one of its files is.
     @param list<string> $targets an edge's, or a value object's reference's */
  public function takes(array $targets, string $type): bool
  {
    return array_any($this->emittedAs($type), fn (string $class): bool => $this->within($targets, $class));
  }

  /** @param list<string> $classes @return bool whether the class is one of them or below one */
  public function within(array $classes, string $class): bool
  {
    return array_intersect(['*', $class, ...$this->vocabulary->ancestorsOf($class)], $classes) !== [];
  }

  /* The classes a record of the type leaves the document as: itself, and for the
     library each class its format derives (`Seed\Media::emittedType`).
     @return list<string> */
  public function emittedAs(string $type): array
  {
    return [$type, ...($type === 'MediaObject' ? array_values(Media::EMITTED) : [])];
  }

  /* The kinds of file a field takes of the library, `image`, by the classes its
     targets are: empty where any file stands, which is what the picker's search,
     its upload, a save and a replaced file all ask.
     @param list<string> $targets @return list<string> */
  public function fileKinds(array $targets): array
  {
    return $this->within($targets, 'MediaObject') ? [] : array_keys(array_filter(Media::EMITTED,
      fn (string $class): bool => $this->within($targets, $class)));
  }

  /* What an editor suggests for a record's kind: every class a write would take,
     by name, since a list closed on the screen and open at the address lets a
     bakery say so through a key and not by hand. No kind leads: which one a
     house needs is not the profile's to guess.
     @return list<string> */
  public function kindsOf(TypeDefinition $type): array
  {
    return $this->vocabulary->descendantsOf($type->name);
  }

  /* A record's kind is a class the vocabulary files under its type. */
  public function specialises(string $name, string $type): bool
  {
    return $this->vocabulary->hasType($name) && in_array($type, $this->vocabulary->ancestorsOf($name), true);
  }

  /* Does the type — or one of its ancestors — carry this property? A property
     the vocabulary does not list is not this type's: `additionalProperty` on an
     Organization answers false, which is the declared off-domain step showing. */
  public function belongsTo(string $property, string $type): bool
  {
    $declared = $this->vocabulary->domainsOf($property);
    if ($declared === null) {
      return false;
    }

    return array_intersect($declared, [$type, ...$this->vocabulary->ancestorsOf($type)]) !== [];
  }
}
