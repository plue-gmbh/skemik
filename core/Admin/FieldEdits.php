<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Relation;
use Feed\Spelling;
use Http\Request;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Kinds;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionField;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\JsonFailed;

/* What a save sends, applied to the record's fields; the spine is `SpineEdits`'s
   and a reference `EdgeEdits`'s. A value lands in `props`; a list arrives whole,
   its blank entries and an object's blank keys dropped, since an empty line in
   the editor is not a value. A labelled thing's words in another house locale
   arrive under `l10n` and are its fields' own there, each written as its field
   is written, and a reference's there are what its edges say in that locale. A
   link under the house's own address lands as its path, as the address stores
   one. A field nobody authors is refused, since an editor not drawn is not a
   rule. */
final readonly class FieldEdits
{
  public function __construct(
    private EdgeEdits $edgeEdits,
    private SchemaOrg $schema,
    private Profile $profile,
    private SettingStore $settings,
    private Clock $clock,
    private string $origin,
    private Json $json = new Json(),
  ) {}

  /** @return array<array-key, mixed> the body, which is one JSON object */
  public function sent(Request $request): array
  {
    try {
      $sent = $this->json->decode($request->body);
    } catch (JsonFailed) {
      throw MalformedValue::notA('a JSON object of changed values', 'the body');
    }

    return $sent !== [] && array_is_list($sent)
      ? throw MalformedValue::notA('a JSON object of changed values', 'the body') : $sent;
  }

  /**
   * @param list<Relation> $stored the record's outbound edges as the page read them
   * @param array<array-key, mixed> $sent the changed fields, the spine left out
   * @return array{Props, list<Relation>} the record's values and every outbound edge, as they will stand
   */
  public function applied(Account $account, Collection $collection, TypeDefinition $type, EntityId $subject,
    Props $props, array $stored, array $sent): array
  {
    $held = $props->document();
    $edges = $stored;
    foreach ($sent as $key => $value) {
      $field = $this->writable($collection, $type, (string) $key);
      if ($field->cardinality->isEdge()) {
        $edges = $this->edgeEdits->replaced($account, $field, $subject, $edges, $value);
      } else {
        $props = $props->with($field->key, $this->stored($field, $field->kind === FieldKind::Objects
          ? $this->referring($account, $field, $props->list($field->key), $value)
          : $this->referred($account, $field, $value, $held[$field->key] ?? null)));
      }
    }

    return [new Props($type->named($held, $props->document())), $edges];
  }

  /* A flattened key's reference as the picker sends it: `{id}` is a hit of the
     search, a chip sent back keeps the record held, and a word is the word. */
  private function referred(Account $account, FieldDefinition $field, mixed $sent, mixed $held): mixed
  {
    if ($this->profile->referring($field) === null || !is_array($sent)) {
      return $sent;
    }
    $id = $sent['id'] ?? null;

    return is_string($id) ? ['@id' => $this->accepted($account, $field, $id)] : $held;
  }

  /* A row's reference as the picker sends it: `{row}` keeps the record the row
     at that place held, whoever may read it, and `{id}` is a hit of the search.
     @param list<mixed> $held the field's rows as stored */
  private function referring(Account $account, FieldDefinition $field, array $held, mixed $sent): mixed
  {
    $object = $this->profile->referring($field);
    $key = $object?->reference;
    foreach ($key === null || !is_array($sent) ? [] : $sent as $index => $row) {
      $picked = is_array($row) && is_array($row[$key] ?? null) ? $row[$key] : [];
      $id = is_int($picked['row'] ?? null) ? $object->referredBy($held[$picked['row']] ?? null)
        : $picked['id'] ?? null;
      if (is_string($id)) {
        $sent[$index][$key] = ['@id' => isset($picked['row']) ? $id
          : $this->accepted($account, $field, $id)];
      }
    }

    return $sent;
  }

  private function accepted(Account $account, FieldDefinition $field, string $id): string
  {
    [$target, $type] = $this->edgeEdits->record($account, $field, $id);
    $targets = $this->profile->referring($field)?->targets ?? [];
    if (!$this->schema->takes($targets, $type)) {
      throw MalformedValue::named(implode(' or ', $targets) . ' for ' . $field->key, $id);
    }

    return $target->value;
  }

  /* One pending change per field per locale: an empty one returns the field to
     the authored words, as `LocalisedText` reads it.
     @param list<Relation> $edges every outbound edge, as the save leaves them
     @return array{Entity, list<Relation>} */
  public function worded(Collection $collection, Entity $entity, array $edges, mixed $sent): array
  {
    if ($sent === null) {
      return [$entity, $edges];
    }
    if (!is_array($sent) || array_is_list($sent)) {
      throw MalformedValue::notA('a map of locales', LocalisedText::KEY);
    }
    $localised = new LocalisedText($this->profile->byType($entity->type));
    foreach ($sent as $tag => $words) {
      $locale = array_find($this->settings->locales(), fn (Locale $each): bool => $each->tag === (string) $tag)
        ?? throw MalformedValue::named('locale of this house', (string) $tag);
      $entity = $localised->write($entity, $locale, $this->words($collection, $entity, $words));
      $edges = $this->spoken($collection, $entity, $locale, $edges, (array) $words);
    }

    return [$entity, $edges];
  }

  /* A reference's words in that locale, on a type that has words per locale.
     @param list<Relation> $edges
     @param array<array-key, mixed> $sent
     @return list<Relation> */
  private function spoken(Collection $collection, Entity $entity, Locale $locale, array $edges, array $sent): array
  {
    $type = $this->profile->byType($entity->type);
    foreach ($sent as $key => $value) {
      $field = $this->writable($collection, $type, (string) $key);
      if ($field->cardinality->isEdge()) {
        $edges = $type->locale->localises()
          ? $this->edgeEdits->worded($field, $locale->tag === $entity->locale->tag ? null : $locale, $edges, $value)
          : throw MalformedValue::named('translatable field of ' . $type->name, $field->key);
      }
    }

    return $edges;
  }

  /** @return array<string, string|list<string|array<string, mixed>>|null> */
  private function words(Collection $collection, Entity $entity, mixed $sent): array
  {
    if (!is_array($sent) || ($sent !== [] && array_is_list($sent))) {
      throw MalformedValue::notA('an object of words', LocalisedText::KEY);
    }
    $words = [];
    foreach ($sent as $key => $value) {
      $field = $this->writable($collection, $this->profile->byType($entity->type), (string) $key);
      if ($field->cardinality->isEdge()) {
        continue;
      }
      $stored = $this->stored($field, $value);
      $each = fn (mixed $entry): bool => $field->kind === FieldKind::Objects ? is_array($entry) : is_string($entry);
      $worded = is_string($stored) || $stored === null || (is_array($stored) && array_all($stored, $each));
      $words[$field->key] = $worded ? $stored : throw MalformedValue::notA('words', $field->key);
    }

    return $words;
  }

  private function writable(Collection $collection, TypeDefinition $type, string $key): FieldDefinition
  {
    $held = array_find($collection->fields, fn (CollectionField $field): bool => $field->key === $key);
    $field = $held === null ? null : $type->field($key);
    if ($field === null || !$field->writtenBy->hasEditor()) {
      throw MalformedValue::named('field a person writes in ' . $collection->name, $key);
    }

    return $field;
  }

  /* Decoded JSON, before the Validator has read it: the one place `mixed` is honest.
     @return string|int|float|bool|array<array-key, mixed>|null */
  private function stored(FieldDefinition $field, mixed $value): string|int|float|bool|array|null
  {
    $blank = fn (mixed $each): bool => in_array($each, [null, '', []], true);
    if ($field->repeatable && !$blank($value) && !(is_array($value) && array_is_list($value))) {
      $value = [$value];
    }
    if ($field->repeatable && is_array($value)) {
      $object = $this->profile->valueObject((string) $field->ref);
      $entries = array_map(fn (mixed $entry): mixed => is_array($entry)
        ? Kinds::spelledRow($object, array_filter($entry, fn (mixed $each): bool => !$blank($each)))
        : $entry, $value);
      $entries = array_values(array_filter($entries, fn (mixed $entry): bool => !$blank($entry)));

      return $entries === [] ? null : Spelling::held($field, $entries, $this->origin);
    }
    if ($blank($value)) {
      return null;
    }
    if ($field->kind === FieldKind::DateTime && is_string($value) && !str_ends_with($value, 'Z')) {
      return $this->clock->parse($value);
    }

    return Spelling::held($field, $value, $this->origin);
  }
}
