<?php

declare(strict_types=1);

namespace Ingest;

use Domain\Entity;
use Domain\EntityId;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Relation;
use Domain\Violation;
use Profile\Qualifier;
use Profile\TypeDefinition;

/* One document, mapped and resolved: the values it sets and the edges it
   replaces predicate by predicate, a list whole. What it does not name stays as
   stored, which is why one class serves a create, a `PATCH` and a repeated order
   alike. Words sent on another locale's route are that locale's, the way the
   feed read them out, a labelled thing's and an edge's alike; the ones it read
   out in their place, the authored words, are none of its own, or writing back a
   read would freeze them there when the authored ones change. */
final readonly class Payload
{
  /**
   * @param array<string, string|int|float|bool|array<array-key, mixed>|null> $values by field key; null removes
   * @param array<string, list<Relation>> $edges by predicate, each replacing that predicate's edges
   * @param list<Violation> $refused what the document says that could not be read, each by its name
   * @param ?EntityId $original the record this one translates, where the document names one
   * @param ?string $language the `inLanguage` the document states, where it states one
   */
  public function __construct(
    private TypeDefinition $type,
    private Locale $locale,
    private array $values,
    private array $edges,
    private array $refused = [],
    private ?EntityId $original = null,
    private ?string $language = null,
  ) {}

  /* The language a document states of a type that forks is the record's own: the route's for a new
     work, and a photograph's or a dataset's wherever it is read, a new one's too
     (`IngestController::spoken`), since there it is the language of the bytes
     (`Profile\LocaleMode`).
     @return list<Violation> what the document says that could not be read, each by its name */
  public function refusedOn(Entity $record): array
  {
    $stated = $this->language === null || $this->language === $record->locale->tag ? []
      : [new Violation('inLanguage', 'not the language of this record: ' . $this->language)];

    return [...$this->refused, ...$stated];
  }

  public function onto(Entity $record): Entity
  {
    $localised = new LocalisedText($this->type);
    $elsewhere = $this->locale->tag !== $record->locale->tag;
    $props = $record->props;
    $words = [];
    foreach ($this->values as $key => $value) {
      if ($elsewhere && in_array($key, $localised->fields(), true)) {
        $words[$key] = $value === ($record->props->document()[$key] ?? null) ? null : $value;
      } else {
        $props = $props->with((string) $key, $value);
      }
    }
    $record = $record->withProps($props);
    $record = $this->original === null ? $record : $record->withTranslationOf($this->original);

    return $words === [] ? $record : $localised->write($record, $this->locale, $words);
  }

  /**
   * @param list<Relation> $stored every outbound edge of the record as it stands
   * @return list<Relation>
   */
  public function edges(Entity $record, array $stored): array
  {
    $kept = array_filter($stored, fn (Relation $edge): bool => !isset($this->edges[$edge->predicate]));
    $sent = array_map(fn (Relation $edge): Relation => $this->worded($record, $edge, $stored),
      array_merge(...array_values($this->edges)));

    return [...array_values($kept), ...$sent];
  }

  /* An edge's words in every other locale stay while its target does. Sent on
     another locale's route, its words are that locale's, and the ones it was
     written with stay; an edge not held before takes them as its own.
     @param list<Relation> $stored */
  private function worded(Entity $record, Relation $sent, array $stored): Relation
  {
    $held = array_find($stored, fn (Relation $edge): bool
      => $edge->predicate === $sent->predicate && $edge->object == $sent->object);
    $before = $held?->qualifiers->document() ?? [];
    $said = $sent->qualifiers->document();
    $words = array_flip(Qualifier::words());
    $translations = $before[LocalisedText::KEY] ?? [];
    if ($held !== null && $this->locale->tag !== $record->locale->tag) {
      $translations[$this->locale->tag] = array_filter(array_intersect_key($said, $words),
        fn (mixed $value, string $key): bool => $value !== ($before[$key] ?? null), ARRAY_FILTER_USE_BOTH);
      $said = [...array_diff_key($said, $words), ...array_intersect_key($before, $words)];
    }
    $translations = array_filter($translations);

    return $sent->withQualifiers(new Props($translations === [] ? $said
      : [...$said, LocalisedText::KEY => $translations]));
  }
}
