<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Domain\Exceptions\AskedTooMuch;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Slug;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Support\Clock;
use Support\Json;

/* A word table of our own, `entity_word`, and not FTS5, which a webspace's
   SQLite may be built without. A search is asked of the fields its reader reads,
   as a filter is, so a `?q=` cannot find a record by a word the reading
   withholds. The words are derived from `props`, each translation's too, and
   the slug, which every reading's address shows. A tag is matched through its
   edge when asked and never copied into the tagged record, since a renamed
   code would leave every copy stale. A tag counts by its code, the one word of
   it the record's document shows, and only where the term is public, the
   reading every reader shares, so a draft term finds nothing for anybody. What
   is typed matches as prefixes through an indexed range and never `like`, so a
   stray quote finds nothing rather than failing. */
final readonly class Search
{
  private const LIMIT = 200;
  /* Every word scans its own stretch of the word table, so a search of more would
     cost a stranger's one question more than a page of 200 does. */
  private const WORDS = 20;
  private const GERMAN = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];

  public function __construct(private \PDO $db, private Profile $profile,
    private SchemaOrg $schema, private Clock $clock, private Json $json = new Json()) {}

  /* The records matching a question, the placeholder holding what `match()`
     answers and `:now` the clock: every term as a prefix in any of the reader's
     fields or tags, all of them met, the most matching words first. The upper
     bound is the prefix followed by the last code point, below which every
     longer word with it sorts. The terms lead by `cross join`, which SQLite never
     reorders: led by the word table, it scans every word of the house per search. */
  public static function hits(string $placeholder = ':match'): string
  {
    $words = "json_each($placeholder, '\$.terms') t cross join entity_word w"
      . ' on w.word >= t.value and w.word < t.value || char(1114111)';

    return "select entity_id, count(*) as hits from (select w.entity_id, t.key as term from $words"
      . " where w.field in (select value from json_each($placeholder, '\$.fields'))"
      . " union all select r.subject_id, t.key from $words"
      . " cross join relation r on r.object_id = w.entity_id and w.field = 'term_code'"
      . " where r.predicate in (select value from json_each($placeholder, '\$.tags'))"
      . ' and exists (select 1 from entity e where e.id = w.entity_id and e.deleted_at is null and '
      . Query::PUBLIC . ')) group by entity_id'
      . " having count(distinct term) = json_array_length($placeholder, '\$.terms')";
  }

  /* The authored words and every translation of them, per field, and the slug's. */
  public function words(TypeDefinition $type, Props $props, Slug $slug): string
  {
    $document = $props->document();
    $translations = array_filter((array) ($document[LocalisedText::KEY] ?? []), is_array(...));
    $words = [];
    foreach ($type->fields() as $field) {
      $held = [$document[$field->key] ?? null, ...array_column($translations, $field->key)];
      $entries = fn (mixed $value): array => SortKeys::entries($field, $value);
      $tokens = $field->cardinality === Cardinality::S && $field->kind->isSearched()
        ? $this->indexed(implode("\n", array_merge(...array_map($entries, $held))))
        : [];
      $tokens = $field->kind === FieldKind::Text && $field->holdsWords() ? $this->endings($tokens) : $tokens;
      $words = $tokens === [] ? $words : [...$words, $field->key => $tokens];
    }

    return $this->json->encode((object) [...$words, 'slug' => $this->indexed($slug->value)]);
  }

  /**
   * @param array<string, list<string>> $readings per type URL, the field keys its reader reads
   * @return list<EntityId> best match first, trashed records left out
   */
  public function search(string $typed, array $readings): array
  {
    $arms = [];
    $bindings = ['now' => $this->clock->now()->format(Clock::STORED)];
    foreach (array_keys($readings) as $index => $type) {
      $arms[] = sprintf('select e.id, s.hits from (%1$s) s join entity e on e.id = s.entity_id'
        . ' where e.type = :type%2$d and e.deleted_at is null',
        self::hits(":match$index"), $index);
      $bindings += ["match$index" => $this->match($typed, $readings[$type]), "type$index" => $type];
    }
    if ($arms === []) {
      return [];
    }
    $statement = $this->db->prepare('select id from (' . implode(' union all ', $arms) . ') order by hits desc'
      . ' limit ' . self::LIMIT);
    $statement->execute($bindings);

    return array_map(fn (string $id): EntityId => new EntityId($id), $statement->fetchAll(\PDO::FETCH_COLUMN));
  }

  /* Nothing typed that is a word, or nothing read that holds words, matches
     nothing rather than failing.
     @param list<string> $read the field keys the reader reads */
  public function match(string $typed, array $read): string
  {
    $terms = $this->tokens($typed);
    if (count($terms) > self::WORDS) {
      throw AskedTooMuch::words(self::WORDS);
    }

    return $this->json->encode(['terms' => $terms,
      'fields' => ['slug', ...array_values(array_intersect($this->profile->searchedKeys(), $read))],
      'tags' => $this->tags($read)]);
  }

  /* @param list<string> $read
     @return list<string> the predicates of the term fields among them, as the edges hold them */
  private function tags(array $read): array
  {
    $fields = array_merge(...array_map(fn (TypeDefinition $type): array => $type->fields(),
      $this->profile->types()));
    $tags = array_filter($fields, fn (FieldDefinition $field): bool
      => $field->cardinality === Cardinality::T && in_array($field->key, $read, true));

    return array_values(array_unique(array_map(fn (FieldDefinition $field): string
      => (string) $this->schema->propertyName($field->key), $tags)));
  }

  /* Lower-case words with their diacritics folded, as a search is typed, so
     `Ärztin` is `arztin` whether typed with the umlaut or without.
     @return list<string> */
  private function tokens(string $text): array
  {
    $decomposed = (string) \Normalizer::normalize(mb_strtolower($text), \Normalizer::FORM_D);
    $folded = (string) preg_replace('/\p{Mn}/u', '', $decomposed);

    return preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  }

  /* The folded words, and beside each that differs in German spelling that
     spelling too, since `Müller` is typed `mueller` as often as `muller`.
     @return list<string> */
  private function indexed(string $text): array
  {
    $composed = (string) \Normalizer::normalize(mb_strtolower($text), \Normalizer::FORM_C);
    $folded = $this->tokens($composed);

    return [...$folded, ...array_values(array_diff($this->tokens(strtr($composed, self::GERMAN)), $folded))];
  }

  /* A short text of words names something, and a name is often one compound word, *Grundkurs* or
     *itemlist.jpg*, so its words keep every ending as a word too and the prefix range finds `kurs` inside. A
     code or a hash is typed from its start, and a long text keeps its words whole: it holds most of the table,
     which this would make several times its size, and a fragment would match nearly every text.
     @param list<string> $words
     @return list<string> each word, then every ending of it */
  private function endings(array $words): array
  {
    $kept = [];
    foreach ($words as $word) {
      for ($start = 0; $start < mb_strlen($word); $start++) {
        $kept[] = mb_substr($word, $start);
      }
    }

    return array_values(array_unique($kept));
  }
}
