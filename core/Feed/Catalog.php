<?php

declare(strict_types=1);

namespace Feed;

use Domain\Locale;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionField;

/* The holdings as one `DataCatalog`, a function of the caller: a collection
   missing from one reading is not absent from the house. Each collection is a
   `DataFeed` carrying its fields' names in `variableMeasured`, so a page prints
   Contact without typing it, one request per locale and nothing added to a
   record; schema.org's enumeration members carry their words under
   `valueReference`, since schema.org publishes no translations of them and no
   runtime holds a table for `EventCancelled`. A key that arranges a collection,
   and the dump of a colleague who may, reads its entry as the arrangement, each
   field flagged `isAccessibleForFree`:
   the one off-domain step, recorded in `Profile\Claims`, because a private key
   would be the second vocabulary the flag is there to avoid. */
final readonly class Catalog
{
  private const CONTEXT = 'https://schema.org';

  /** @param list<string> $arranged the collections the caller arranges, by slug */
  public function __construct(
    private Profile $profile,
    private Variables $variables,
    private PublicFilter $filter,
    private string $origin,
    private array $arranged = [],
  ) {}

  /* The house's one date, said on the smallest document and on no entry, which
     would claim a date per collection it is not (`Storage\Modified`).
     @param list<Locale> $locales the house's, the first of them its default
     @return array<string, mixed> */
  public function locales(array $locales, string $modified): array
  {
    $datasets = [];
    foreach ($locales as $locale) {
      $datasets[] = ['@type' => 'DataCatalog', '@id' => $this->origin . '/feed/' . $locale->tag,
        'name' => $locale->tag, 'inLanguage' => $locale->tag];
    }

    return ['@context' => self::CONTEXT, '@id' => $this->origin . '/feed', '@type' => 'DataCatalog',
      'name' => $this->name(), 'dateModified' => $modified, 'dataset' => $datasets];
  }

  /**
   * @param list<Collection> $collections every collection of the house
   * @return array<string, mixed>
   */
  public function collections(array $collections, Locale $locale): array
  {
    $datasets = [];
    foreach ($collections as $collection) {
      if ($this->filter->reads($collection) || $this->arranges($collection)) {
        $datasets[] = $this->feed($collection, $locale);
      }
    }

    return ['@context' => self::CONTEXT, '@id' => $this->origin . '/feed/' . $locale->tag,
      '@type' => 'DataCatalog', 'name' => $this->name(), 'inLanguage' => $locale->tag,
      'dataset' => $datasets];
  }

  /* One collection's entry as a document of its own: what an arrangement answers.
     @return array<string, mixed> */
  public function entry(Collection $collection, Locale $locale): array
  {
    return ['@context' => self::CONTEXT, ...$this->feed($collection, $locale)];
  }

  /** @return array<string, mixed> */
  private function feed(Collection $collection, Locale $locale): array
  {
    $type = $this->profile->byType($collection->type);
    $variables = $this->arranges($collection) ? $this->arrangement($collection, $type, $locale)
      : $this->reading($collection, $type, $locale);

    return ['@type' => 'DataFeed', '@id' => $this->origin . '/feed/' . $locale->tag . '/'
      . $collection->slug->value, 'name' => basename($collection->type), 'about' => $type->url(),
      'encodingFormat' => 'application/ld+json', 'variableMeasured' => $variables];
  }

  /** @return list<array<string, mixed>> the fields this caller reads */
  private function reading(Collection $collection, TypeDefinition $type, Locale $locale): array
  {
    $variables = [];
    foreach ($this->filter->fields($collection) as $held) {
      $field = $type->field($held->key);
      if ($field !== null) {
        $variables[] = $this->variables->variable($field, $locale);
      }
    }

    return $variables;
  }

  /** @return list<array<string, mixed>> every field carried, each flagged where it leaves the house */
  private function arrangement(Collection $collection, TypeDefinition $type, Locale $locale): array
  {
    $carried = array_filter(array_map(fn (CollectionField $held): ?FieldDefinition
      => $type->field($held->key), $collection->fields));
    $out = array_column($collection->fields, 'feed', 'key');
    $flag = fn (FieldDefinition $field, bool $whole): array
      => $whole ? [] : ['isAccessibleForFree' => $out[$field->key]];

    return $this->variables->grouped(array_values($carried), $locale, $flag);
  }

  private function arranges(Collection $collection): bool
  {
    return in_array($collection->slug->value, $this->arranged, true);
  }

  /* The house answers under the address it was asked at, which is the one fact
     about itself an install never has to be told. */
  private function name(): string
  {
    return (string) parse_url($this->origin, PHP_URL_HOST);
  }
}
