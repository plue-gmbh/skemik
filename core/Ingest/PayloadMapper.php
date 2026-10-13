<?php

declare(strict_types=1);

namespace Ingest;

use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Relation;
use Domain\Slug;
use Domain\Violation;
use Feed\JsonLd;
use Feed\Targets;
use Http\Route;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Seed\Media;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Profile\WrittenBy;
use Storage\Collection;
use Storage\CollectionField;
use Storage\CollectionStore;
use Support\Clock;
use Support\Markup;

/* Maps a JSON-LD or plain JSON body onto profile fields, as the exact inverse of
   `Feed\JsonLd`: a record from a dump posted straight back is an equal record,
   and where the two disagree one of them is a bug. The spine is read as the feed
   writes it: `@type` is the collection's, a kind lifted into it as a page marks
   one up being `additionalType`; the language is the record's where the type
   forks (`Payload`), and on any other the field it names, a message's; a
   translation names the record it translates, which never names it back. What is computed at emit
   is dropped, since nothing stores it. An instant with an offset is one instant
   and is converted to UTC; one without is refused by the `Validator`, never read
   in the house's zone, since an agent is not a person at the editor. A link under
   this origin is stored as its path (`JsonLd::held`). A property no field of
   the type declares is refused by name: kept, it would be stored where no document
   and no screen shows it. Each property that cannot be read is named, not the first. */
final readonly class PayloadMapper
{
  /* Read beside the fields, or passed over. */
  private const SPINE = ['@context', '@id', '@type', 'translationOfWork', 'workTranslation'];

  /** @param \Closure(\Domain\Entity): bool $reads whether the key reads a target */
  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private Targets $targets,
    private CollectionStore $collections,
    private ObjectRows $rows,
    private Edges $edges,
    private string $origin,
    private \Closure $reads,
  ) {}

  /* What a grant is asked about, read before anything is resolved: the fields the
     document writes, by the name it gave each, and the translation it names, which
     joins the record to another's group and is no field's, so only a rule on the
     collection whole covers it, as one does a document naming nothing else. What
     cannot be read is left to the refusal that names it once the grant has been asked.
     @param array<string, mixed> $document
     @return array<string, string> field key to the name the document spelt it by */
  public function fields(Collection $collection, array $document): array
  {
    $type = $this->profile->byType($collection->type);
    $fields = [];
    foreach ($document as $property => $value) {
      try {
        $keys = array_keys($this->fieldsOf($collection, (string) $property, $value));
      } catch (MalformedValue) {
        $keys = [];
      }
      foreach ($keys as $key) {
        $fields[$key] = $this->schema->spelt($type, $key);
      }
    }

    return array_key_exists('translationOfWork', $document)
      ? [...$fields, 'translationOfWork' => 'translationOfWork'] : $fields;
  }

  /** @param array<string, mixed> $document */
  public function map(Route $route, EntityId $subject, array $document): Payload
  {
    $collection = $this->collections->bySlug(new Slug($route->parameter('collection')))
      ?? throw MalformedValue::named('collection', $route->parameter('collection'));
    $locale = new Locale($route->parameter('locale'));
    $values = $relations = $refused = [];
    foreach ($document as $property => $raw) {
      try {
        $found = $this->fieldsOf($collection, (string) $property, $raw);
        $values = [...$values, ...$this->values($collection, $found)];
        $relations = [...$relations, ...$this->relations($collection, $subject, $found)];
      } catch (MalformedValue $refusal) {
        $refused[] = new Violation((string) $property, $refusal->getMessage());
      }
    }
    [$original, $spine] = $this->original($document['translationOfWork'] ?? null);
    $type = $this->profile->byType($collection->type);
    $language = $type->locale->forks() ? $document['inLanguage'] ?? null : null;

    return new Payload($type, $locale, $values, $relations,
      [...$refused, ...$spine], $original, is_string($language) || $language === null ? $language : 'a list');
  }

  /* @return array{?EntityId, list<Violation>} the record translated, or why it names none */
  private function original(mixed $sent): array
  {
    if ($sent === null) {
      return [null, []];
    }
    $original = is_array($sent) && array_is_list($sent) && count($sent) === 1 ? $sent[0] : $sent;
    $address = is_array($original) ? $original['@id'] ?? null : $original;
    $record = is_string($address) ? $this->targets->at($address) : null;
    $read = $record !== null && ($this->reads)($record);

    return $read ? [$record->id, []]
      : [null, [new Violation('translationOfWork', 'not the address of one record')]];
  }

  /* @return array<string, mixed> field key to the value as sent: none for the spine
     or for what is computed at emit */
  private function fieldsOf(Collection $collection, string $property, mixed $value): array
  {
    $type = $this->profile->byType($collection->type);
    /* An asset is read back as the feed spells it, since the file decides. */
    $spellings = [$type->name, ...($type->name === 'MediaObject' ? array_values(Media::EMITTED) : [])];
    if ($property === '@type' && !in_array($value, $spellings, true)) {
      throw MalformedValue::named('@type of ' . $collection->name, is_string($value) ? $value : 'a list');
    }
    if (in_array($property, self::SPINE, true) || ($property === 'inLanguage' && $type->locale->forks())) {
      return [];
    }
    $own = $this->schema->belongsTo($property, $type->name);
    $direct = array_find($type->fields(), fn (FieldDefinition $field): bool =>
      ($field->valueObject === null || ($field->valueObjectOf === null && $own))
      && $this->schema->propertyName($field->key) === $property);
    if ($direct !== null) {
      return $direct->writtenBy === WrittenBy::Derived ? [] : [$direct->key => $value];
    }
    $carried = array_values(array_filter($type->fields(),
      fn (FieldDefinition $field): bool => $field->valueObjectOf === $property));

    return $carried === [] ? throw MalformedValue::named('property of ' . $collection->name, $property)
      : $this->flattened($type, $carried, $value);
  }

  /* @param array<string, mixed> $found
     @return array<string, string|int|float|bool|array<array-key, mixed>|null> */
  private function values(Collection $collection, array $found): array
  {
    $type = $this->profile->byType($collection->type);
    $values = [];
    foreach ($found as $key => $raw) {
      $field = $this->carried($collection, $type, $key);
      if (!$field->cardinality->isEdge()) {
        $values[$key] = $this->stored($field, $raw);
      }
    }

    return $values;
  }

  /* @param array<string, mixed> $found
     @return array<string, list<Relation>> by predicate */
  private function relations(Collection $collection, EntityId $subject, array $found): array
  {
    $type = $this->profile->byType($collection->type);
    $relations = [];
    foreach ($found as $key => $raw) {
      $field = $this->carried($collection, $type, $key);
      if ($field->cardinality->isEdge()) {
        $relations[(string) $this->schema->propertyName($key)] = $this->edges->relations($field, $subject, $raw);
      }
    }

    return $relations;
  }

  /* `Feed\JsonLd`'s assembly in reverse: one nested object back into the fields it was assembled
     from, a key no field of this property carries being the one its object
     shares with the type's other amounts (an invoice's currency).
     @param non-empty-list<FieldDefinition> $carried
     @return array<string, mixed> */
  private function flattened(TypeDefinition $type, array $carried, mixed $value): array
  {
    if ($value === null) {
      return array_fill_keys(array_map(fn (FieldDefinition $field): string => $field->key, $carried), null);
    }
    $object = (string) $carried[0]->valueObject;
    if (!is_array($value) || array_is_list($value)) {
      throw MalformedValue::notA('a ' . $object, (string) $carried[0]->valueObjectOf);
    }
    $shared = array_filter($type->fields(), fn (FieldDefinition $field): bool =>
      $field->valueObject === $object && $field->valueObjectOf === null);
    $fields = [];
    foreach (array_diff_key($value, ['@type' => true]) as $key => $inner) {
      $field = array_find([...$carried, ...$shared],
        fn (FieldDefinition $field): bool => $field->valueObjectKey === $key)
        ?? throw MalformedValue::named('key of ' . $object, (string) $key);
      $fields[$field->key] = $inner;
    }

    return $fields;
  }

  /* A field the collection does not carry would be stored where no screen shows
     it. The refusal names it as the document spelt it, not as it is stored. */
  private function carried(Collection $collection, TypeDefinition $type, string $key): FieldDefinition
  {
    $held = array_any($collection->fields, fn (CollectionField $field): bool => $field->key === $key);
    $field = $held ? $type->field($key) : null;

    return $field ?? throw MalformedValue::named('field of ' . $collection->name,
      $this->schema->propertyName($key) ?? $key);
  }

  /** @return string|int|float|bool|array<array-key, mixed>|null */
  private function stored(FieldDefinition $field, mixed $raw): string|int|float|bool|array|null
  {
    $single = $field->repeatable && $field->kind !== FieldKind::Objects
      && !(is_array($raw) && array_is_list($raw));

    return match (true) {
      $raw === '' || $raw === null => null,
      $single => [JsonLd::held($field, $this->rows->held($field, $raw), $this->origin)],
      $field->kind === FieldKind::Objects && is_array($raw) => $this->rows->stored($field, $raw),
      $field->kind === FieldKind::DateTime && is_string($raw) => $this->utc($raw),
      $field->kind === FieldKind::Markdown && is_string($raw) => $this->linked($raw),
      default => JsonLd::held($field, $this->rows->held($field, $raw), $this->origin),
    };
  }

  /* A text's link to a record the key reads is held by its id, as the admin
     writes one, so a dump posted back links as it did and a slug change breaks
     nothing; one the key does not read stays the address it sent. */
  private function linked(string $markdown): string
  {
    return Markup::relinked($markdown, function (string $address): string {
      $record = $this->targets->at($address);

      return $record !== null && ($this->reads)($record) ? Markup::RECORD . $record->id->value : $address;
    });
  }

  /* A fraction of a second is dropped, since an instant is held to the second
     and JavaScript's `toISOString()` always sends one. */
  private function utc(string $sent): string
  {
    $whole = (string) preg_replace('/^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d)\.\d+(?=Z$|[+-]\d\d:\d\d$)/', '$1', $sent);
    $instant = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $whole);

    return $instant === false || $instant->format(\DATE_ATOM) !== $whole
      ? $whole
      : $instant->setTimezone(new \DateTimeZone('UTC'))->format(Clock::STORED);
  }
}
