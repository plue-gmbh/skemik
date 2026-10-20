<?php

declare(strict_types=1);

namespace Feed;

use Domain\Locale;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\LocaleMode;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Profile\ValueObject;
use Storage\Collection;

/* The profile at `/feed/{locale}/profile`: every field of the thirty types,
   built from the release and never from the house, so it is the same in every
   install and a machine learns a type without probing a catalogue that shows
   only its own reading. A field is the catalogue's entry typed a `Property` too,
   and no `@id` joins it to the catalogue's, which lists what one house carries.
   What a write must send, may repeat and cannot set is annotated on a
   `CreateAction`'s `result`, as schema.org annotates an action, nested as a
   document nests it (`Properties`). `inLanguage` on an entry has no released
   home on a `PropertyValue`, recorded in `Profile\Claims` beside the catalogue's
   `isAccessibleForFree`. */
final readonly class ProfileCatalog
{
  private const CONTEXT = 'https://schema.org';
  private const FIELD = ['PropertyValue', 'Property'];
  private const SPECIFICATION = 'PropertyValueSpecification';

  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private Variables $variables,
    private string $origin,
  ) {}

  /** @return array<string, mixed> */
  public function document(Locale $locale): array
  {
    $feeds = array_map(fn (TypeDefinition $type): array => $this->feed($type, $locale),
      $this->profile->types());

    return ['@context' => self::CONTEXT, '@id' => $this->prefix($locale) . 'profile',
      '@type' => 'DataCatalog', 'inLanguage' => $locale->tag, 'dataset' => $feeds];
  }

  /** @return array<string, mixed> */
  private function feed(TypeDefinition $type, Locale $locale): array
  {
    $address = $this->prefix($locale) . Collection::SLUGS[$type->name];
    $entry = fn (FieldDefinition $field, bool $whole): array => $whole
      ? $this->property([(string) $field->valueObject]) : $this->entry($type, $field, $locale);

    return ['@type' => 'DataFeed', 'url' => $address, 'name' => $type->name,
      'about' => ['@type' => 'Class', '@id' => $type->url(), 'name' => $type->name],
      'encodingFormat' => 'application/ld+json',
      'variableMeasured' => $this->variables->grouped($type->fields(), $locale, $entry),
      'potentialAction' => ['@type' => 'CreateAction', 'target' => $address,
        'result' => $this->created($type)]];
  }

  /* A row's keys under an objects field, and the route's language where the
     field's words differ by route: never on a type whose second language is a
     second one of it, a review.
     @return array<string, mixed> */
  private function entry(TypeDefinition $type, FieldDefinition $field, Locale $locale): array
  {
    $rows = $field->kind === FieldKind::Objects ? $this->profile->valueObject((string) $field->ref) : null;
    $worded = $field->holdsWords() && $type->locale !== LocaleMode::None;

    return [...$this->property($this->range($field)),
      ...($rows === null ? [] : ['valueReference' => $this->rows($rows, $field, $locale)]),
      ...($worded ? ['inLanguage' => $locale->tag] : [])];
  }

  /** @param list<string> $range @return array<string, mixed> */
  private function property(array $range): array
  {
    return ['@type' => self::FIELD,
      'rangeIncludes' => array_map(fn (string $class): string => 'https://schema.org/' . $class, $range)];
  }

  /** @return list<string> */
  private function range(FieldDefinition $field): array
  {
    return match (true) {
      $field->cardinality->isEdge() => array_map(fn (string $target): string
        => $target === '*' ? 'Thing' : $target, $field->targets),
      $field->kind === FieldKind::Objects => [(string) $field->ref],
      default => $this->schema->holds($field->kind, $field->options, $field->whole),
    };
  }

  /* A key that takes a closed list carries its words, as a select's entry does.
     @return list<array<string, mixed>> */
  private function rows(ValueObject $object, FieldDefinition $field, Locale $locale): array
  {
    $range = fn (string $key): array => $key === $object->reference ? $object->targets
      : $this->schema->holds($object->kinds[$key] ?? FieldKind::Text, $object->options[$key] ?? [],
        in_array($key, $object->whole, true));
    $words = fn (string $key): array => isset($object->options[$key])
      ? ['valueReference' => $this->variables->worded($field, $object->options[$key], $locale)] : [];

    return array_map(fn (string $key): array => [...$this->property($range($key)), 'propertyID' => $key,
      ...$words($key)], $field->carried($object));
  }

  /* An edge is annotated apart from the values, since a value object may arrive
     on its property too: an event's place beside its online address. An edge
     that is a value object's key is nested with the object's other keys.
     @return array<string, mixed> */
  private function created(TypeDefinition $type): array
  {
    $values = [];
    $edges = [];
    foreach ($type->fields() as $field) {
      if ($field->cardinality->isEdge() && $field->valueObject === null) {
        $edges[$this->schema->propertyName($field->key) . '-input'] = $this->specification($field);
        continue;
      }
      $values[$field->key] = $this->specification($field);
    }
    $properties = (new Properties($this->profile, $this->schema))->of($type, $values);

    return ['@type' => $type->name, ...$this->inputs($properties), ...$edges];
  }

  /* schema.org's default for each is false, so only what holds is said.
     @return array<string, mixed> */
  private function specification(FieldDefinition $field): array
  {
    return ['@type' => self::SPECIFICATION, ...array_filter(['valueRequired' => $field->required,
      'multipleValues' => $field->holdsMany(), 'readonlyValue' => !$field->writtenBy->isStored()])];
  }

  /** @param array<array-key, mixed> $properties @return array<array-key, mixed> */
  private function inputs(array $properties): array
  {
    $inputs = [];
    foreach ($properties as $name => $value) {
      $annotated = is_array($value) && ($value['@type'] ?? null) === self::SPECIFICATION;
      $inputs[$annotated ? $name . '-input' : $name] = is_array($value) && !$annotated
        ? $this->inputs($value) : $value;
    }

    return $inputs;
  }

  private function prefix(Locale $locale): string
  {
    return $this->origin . '/feed/' . $locale->tag . '/';
  }
}
