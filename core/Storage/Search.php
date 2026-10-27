<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Domain\Exceptions\AskedTooMuch;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Slug;
use Domain\Status;
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
   the slug, which every reading's address shows. What a record points at is
   matched through its edge when asked and never copied, since a renamed target
   would leave every copy stale: a tag by its code where the term is public, the
   reading every reader shares, and a reference's target by its name, one edge
   deep, only where this reader reads that name, since a customer is a draft
   person and a stranger must not learn one's name from a review's author. What
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
     fields, its tags or the names of what it points at, all of them met, the
     most matching words first. The upper bound is the prefix followed by the
     last code point, below which every longer word with it sorts. The terms
     lead by `cross join`, which SQLite never reorders: led by the word table, it
     scans every word of the house per search. */
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
      . Query::PUBLIC . ')' . self::pointed($placeholder, $words) . ') group by entity_id'
      . " having count(distinct term) = json_array_length($placeholder, '\$.terms')";
  }

  /* A word of a target's name reaches each record pointing at it under a predicate
     `match()` named, where the target is of the type and in the reading it named.
     The naming fields are asked of the word first, so a long text's words probe
     no edge. */
  private static function pointed(string $placeholder, string $words): string
  {
    $named = "(select json_extract(n.value, '\$.named') from json_each($placeholder, '\$.edges') n)";

    return " union all select r.subject_id, t.key from $words"
      . ' cross join relation r on r.object_id = w.entity_id'
      . " cross join json_each($placeholder, '\$.edges') g on r.predicate = json_extract(g.value, '\$.predicate')"
      . " and w.field = json_extract(g.value, '\$.named')"
      . " cross join entity e on e.id = w.entity_id and e.type = json_extract(g.value, '\$.type')"
      . ' and e.deleted_at is null'
      . " where w.field in $named and (json_array_length(g.value, '\$.statuses') = 0 or " . Query::STATUS
      . " in (select value from json_each(g.value, '\$.statuses')))"
      . " and (json_array_length(g.value, '\$.ids') = 0 or e.id in (select value from json_each(g.value, '\$.ids')))";
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
   * @param array<string, Query> $readings per type, what the reader reads of it, its fields among that
   * @return list<EntityId> best match first, trashed records left out
   */
  public function search(string $typed, array $readings): array
  {
    $arms = [];
    $bindings = ['now' => $this->clock->now()->format(Clock::STORED)];
    foreach (array_values($readings) as $index => $reading) {
      $type = $this->profile->byType($reading->type);
      $arms[] = sprintf('select e.id, s.hits from (%1$s) s join entity e on e.id = s.entity_id'
        . ' where e.type = :type%2$d and e.deleted_at is null',
        self::hits(":match$index"), $index);
      $bindings += ["match$index" => $this->match($typed, $type, $reading->through($readings)),
        "type$index" => $type->url()];
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
     nothing rather than failing. The query says which fields of the type the
     reader reads and what they read of every type an edge may reach. */
  public function match(string $typed, TypeDefinition $type, Query $query): string
  {
    $terms = $this->tokens($typed);
    if (count($terms) > self::WORDS) {
      throw AskedTooMuch::words(self::WORDS);
    }
    $read = array_values(array_filter($type->fields(), fn (FieldDefinition $field): bool
      => in_array($field->key, $query->searchIn, true)));

    return $this->json->encode(['terms' => $terms,
      'fields' => ['slug', ...array_values(array_intersect($this->profile->searchedKeys(), $query->searchIn))],
      'tags' => $this->tags($read), 'edges' => $this->edges($read, $query->searchThrough)]);
  }

  /* @param list<FieldDefinition> $read
     @return list<string> the predicates of the term fields among them, as the edges hold them */
  private function tags(array $read): array
  {
    $tags = array_filter($read, fn (FieldDefinition $field): bool => $field->cardinality === Cardinality::T);

    return array_values(array_unique(array_map(fn (FieldDefinition $field): string
      => (string) $this->schema->propertyName($field->key), $tags)));
  }

  /* Each reference field among the read, once per type it points at whose name the reader reads: a
     name the reading withholds is not found through what points at it either.
     @param list<FieldDefinition> $read
     @param array<string, Query> $through
     @return list<array{predicate: string, type: string, named: string, statuses: list<string>, ids: list<string>}> */
  private function edges(array $read, array $through): array
  {
    $edges = [];
    foreach ($read as $field) {
      $reference = $field->kind === FieldKind::Ref || $field->kind === FieldKind::Refs;
      foreach ($reference ? $through : [] as $reading) {
        $target = $this->profile->byType($reading->type);
        $named = in_array($target->namedBy(), $reading->searchIn, true);
        if ($named && $this->schema->takes($field->targets, $target->name)) {
          $edges[] = $this->edge($field, $target, $reading);
        }
      }
    }

    return $edges;
  }

  /* The target's type, the field it is named by and the reading: every status
     where the list is empty, and every record where the ids are.
     @return array{predicate: string, type: string, named: string, statuses: list<string>, ids: list<string>} */
  private function edge(FieldDefinition $field, TypeDefinition $target, Query $reading): array
  {
    return ['predicate' => (string) $this->schema->propertyName($field->key), 'type' => $target->url(),
      'named' => $target->namedBy(),
      'statuses' => array_map(fn (Status $status): string => $status->value, $reading->statuses),
      'ids' => $reading->filters['id'] ?? []];
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
