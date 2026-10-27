<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;
use Profile\TypeDefinition;

/* The second of the spine's two language mechanisms, for the types whose `LocaleMode` localises —
   the labelled things, and the two that also fork. The authored value stays
   where it is, so `json_extract(props, '$.name')` is still a string; other
   locales sit under `props.l10n`, and a locale with no words of its own reads
   the authored value, never empty. It opens only the fields a reader reads (`holdsWords`).
   Two classes because they are two mechanisms, and `TypeDefinition::$locale`
   says which a type has, so `MediaObject` or `Dataset` having both is a value
   the type declares and not an `if` in `Admin\RecordController`. */
final readonly class LocalisedText
{
  public const KEY = 'l10n';

  public function __construct(private TypeDefinition $type) {}

  /** @return list<string> */
  public function fields(): array
  {
    if (!$this->type->locale->localises()) {
      return [];
    }
    $keys = [];
    foreach ($this->type->fields() as $field) {
      if ($field->holdsWords() && $field->writtenBy->hasEditor()) {
        $keys[] = $field->key;
      }
    }

    return $keys;
  }

  /** @return string|list<string|array<string, mixed>>|null */
  public function read(Entity $entity, string $key, Locale $locale): string|array|null
  {
    $this->requireOpen($entity, [$key]);
    $authored = $entity->props->document()[$key] ?? null;
    $translated = $this->translations($entity)[$locale->tag][$key] ?? null;

    return in_array($translated, [null, '', []], true) ? $authored : $translated;
  }

  /**
   * Null or empty removes a locale's words; in the authored locale it writes
   * the field itself.
   *
   * @param array<string, string|list<string|array<string, mixed>>|null> $words
   */
  public function write(Entity $entity, Locale $locale, array $words): Entity
  {
    $this->requireOpen($entity, array_keys($words));
    if ($locale->tag === $entity->locale->tag) {
      $props = $entity->props;
      foreach ($words as $key => $value) {
        $props = $props->with($key, in_array($value, ['', []], true) ? null : $value);
      }

      return $entity->withProps($props);
    }
    $translations = $this->translations($entity);
    $inLocale = array_filter([...$translations[$locale->tag] ?? [], ...$words],
      fn (string|array|null $value): bool => !in_array($value, [null, '', []], true));
    $translations[$locale->tag] = $inLocale;

    return $entity->withProps($entity->props->with(self::KEY, array_filter($translations) ?: null));
  }

  /* The record as that locale's route serves it, for a screen to draw. */
  public function shown(Entity $entity, Locale $locale): Entity
  {
    $props = $entity->props;
    foreach ($this->fields() as $key) {
      $props = $props->with($key, $this->read($entity, $key, $locale));
    }

    return $entity->withProps($props);
  }

  /* The record with each translatable field holding that locale's own words
     and nothing where it has none, for an editor to start from. */
  public function own(Entity $entity, Locale $locale): Entity
  {
    $own = $locale->tag === $entity->locale->tag ? [] : $this->translations($entity)[$locale->tag] ?? [];
    $props = $entity->props;
    foreach ($this->fields() as $key) {
      $props = $props->with($key, $own[$key] ?? null);
    }

    return $entity->withProps($props);
  }

  /** @return list<string> the locales holding words of their own beside the authored ones */
  public function locales(Entity $entity): array
  {
    return array_map('strval', array_keys(array_filter($this->translations($entity))));
  }

  /* What a translation starts from on a type with both mechanisms: the words
     kept for its locale become its authored ones, on a record's props and an
     edge's qualifiers alike, and the other locales' stay with the source alone,
     whose routes serve them, so no words stand on two members. */
  public static function folded(Props $props, Locale $locale): Props
  {
    foreach (self::wordsOf($props)[$locale->tag] ?? [] as $key => $value) {
      $props = $props->with((string) $key, $value);
    }

    return $props->with(self::KEY, null);
  }

  /* The same less that locale's words, for every other member of a group: a
     locale a member is authored in is that member's to word, and words standing
     on a second one are words the switch hides and nobody can clear. */
  public static function stripped(Props $props, Locale $locale): Props
  {
    $translations = self::wordsOf($props);
    unset($translations[$locale->tag]);

    return $props->with(self::KEY, $translations === [] ? null : $translations);
  }

  /** @param list<string> $keys */
  private function requireOpen(Entity $entity, array $keys): void
  {
    assert($entity->typeName() === $this->type->name, 'the entity is of another type');
    foreach (array_diff($keys, $this->fields()) as $key) {
      throw MalformedValue::named('translatable field of ' . $this->type->name, $key);
    }
  }

  /** @return array<string, array<string, string|list<string|array<string, mixed>>>> */
  private function translations(Entity $entity): array
  {
    return self::wordsOf($entity->props);
  }

  /** @return array<string, array<string, string|list<string|array<string, mixed>>>> */
  private static function wordsOf(Props $props): array
  {
    $translations = $props->document()[self::KEY] ?? [];
    if (!is_array($translations)) {
      throw MalformedValue::notA('a map of locales', self::KEY);
    }

    return $translations;
  }
}
