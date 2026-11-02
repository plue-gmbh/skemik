<?php

declare(strict_types=1);

namespace Storage;

use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Slug;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\TypeDefinition;
use Support\Json;
use Support\Uuid;
use Support\Words;

/* The seam between the profile and a house: the profile says what an `Article`
   is, a collection says which of its fields this house carries and lets out.
   Every house holds one collection per type (`provide`), so the thirty are on
   every sidebar and a release that adds a type gives every standing house its
   collection: the seed, `/install` and an update all call the same method.
   What would leave a collection unable to hold its type is refused here, not
   only hidden on a screen: a † field removed. A key of a value object is a
   field of its own, carried with or without the others. A field taken off hides
   its values and deletes none, so carrying it again brings them back.
   The name is the chrome's word for the type, read here so nothing above asks. */
final readonly class CollectionStore
{
  public function __construct(
    private \PDO $db,
    private Profile $profile,
    private Uuid $uuid,
    private Words $words,
    private Json $json = new Json(),
  ) {
    assert($this->everyTypeIsPlaced(), 'a type has no address');
  }

  /** @return list<Collection> in the profile's order */
  public function all(): array
  {
    $statement = $this->db->prepare('select * from collection');
    $statement->execute();
    $held = [];
    foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $held[(string) $row['type']] = $row;
    }
    $ordered = [];
    foreach ($this->profile->types() as $type) {
      if (isset($held[$type->url()])) {
        $ordered[] = $this->collection($held[$type->url()]);
      }
    }

    return $ordered;
  }

  public function bySlug(Slug $slug): ?Collection
  {
    $name = array_search($slug->value, Collection::SLUGS, true);
    $statement = $this->db->prepare('select * from collection where type = :type');
    $statement->execute(['type' => 'https://schema.org/' . $name]);
    $row = $name === false ? false : $statement->fetch(\PDO::FETCH_ASSOC);

    return $row === false ? null : $this->collection($row);
  }

  /* Every type this house has no collection of yet gets one: its † and ‡ fields,
     every one withheld. One statement per type, so two requests cannot make two. */
  public function provide(): void
  {
    $statement = $this->db->prepare('insert into collection (id, type, fields)'
      . ' values (:id, :type, :fields) on conflict (type) do nothing');
    foreach ($this->profile->types() as $type) {
      $fields = array_map(fn (FieldDefinition $field): array => ['key' => $field->key, 'feed' => false],
        $type->firstScreen());
      $statement->execute(['id' => $this->uuid->generate(),
        'type' => $type->url(), 'fields' => $this->json->encode($fields)]);
    }
  }

  public function save(Collection $collection): void
  {
    $this->requireHoldable($collection, $this->profile->byType($collection->type));
    $statement = $this->db->prepare('update collection set fields = :fields where id = :id'
      . ' and type = :type');
    $statement->execute(['fields' => $this->fields($collection), 'id' => $collection->id,
      'type' => $collection->type]);
    if ($statement->rowCount() !== 1) {
      throw EntityNotFound::withId($collection->id);
    }
  }

  private function requireHoldable(Collection $collection, TypeDefinition $type): void
  {
    $carried = [];
    foreach ($collection->fields as $field) {
      $type->field($field->key) ?? throw MalformedValue::named('field of ' . $type->name, $field->key);
      $carried[$field->key] = true;
    }
    foreach ($type->fields() as $field) {
      if (!isset($carried[$field->key]) && $field->required) {
        throw MalformedValue::named('collection without the required field', $field->key);
      }
    }
  }

  private function fields(Collection $collection): string
  {
    return $this->json->encode(array_map(fn (CollectionField $field): array => ['key' => $field->key,
      'feed' => $field->feed], $collection->fields));
  }

  /** A key the type no longer names, left by a release that dropped the field, is
      left out as the row is read, since carried it would refuse every save of the
      collection; the next save writes the fields without it.
      @param array<string, string|int|null> $row */
  private function collection(array $row): Collection
  {
    $type = $this->profile->byType((string) $row['type']);
    $fields = array_map(fn (array $field): CollectionField => new CollectionField((string) $field['key'],
      $field['feed'] === true), $this->json->decode((string) $row['fields']));
    $named = array_filter($fields, fn (CollectionField $field): bool => $type->field($field->key) !== null);

    return new Collection((string) $row['id'], (string) $row['type'],
      $this->words->get('type.' . basename((string) $row['type'])), array_values($named));
  }

  private function everyTypeIsPlaced(): bool
  {
    foreach ($this->profile->types() as $type) {
      assert(isset(Collection::SLUGS[$type->name]), $type->name . ' has no address');
    }

    return count(array_unique(Collection::SLUGS)) === count($this->profile->types());
  }
}
