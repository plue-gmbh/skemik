<?php

declare(strict_types=1);

namespace Storage;

use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Status;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Support\Json;

/* A filter, sort and page request, built by `Feed\FeedRequest` and the admin alike
   so the editor's filter is the reader's address. A key is checked against the
   type before any SQL is written, so a bad one is a 400 naming it and never an
   injection, and every key compiles to a `json_extract` or an edge, so no field
   waits for an index. Text compares through `sort_keys` in the words the route
   shows, so an English route never finds a person by a word it does not show; a
   link compares without case, since `Max@` and `max@` are one mailbox. The zone
   is not read here, so a house's day is the caller's to name. Newest created
   first unless asked, since an edit is not news, and the empty last either way,
   since a sort is asked of the values. */
final readonly class Query
{
  public const PER_PAGE = 50;
  public const MAX_PER_PAGE = 200;
  /* No field takes one of these names, which `Profile\Promises` holds. */
  private const SPINE = ['slug', 'status', 'locale', 'created_at', 'updated_at', 'publish_from',
    'publish_to'];
  /* The keys read off the row's columns: `slug` the address the caller writes, `id`
     the one a subject-bound grant binds to, `locale` the language the row is in. */
  private const COLUMNS = ['slug', 'id', 'locale'];
  private const RANGED = [FieldKind::Number, FieldKind::Date, FieldKind::DateTime];
  private const SHAPES = ['date' => '/^\d{4}(-\d{2}){0,2}$/',
    'date_time' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'];
  public const PUBLIC = "(e.status = 'published' and (e.publish_from is null or e.publish_from <= :now)"
    . ' and (e.publish_to is null or :now < e.publish_to))';

  /**
   * @param array<string, list<string>> $filters a field key or a column to the values any of which matches
   * @param list<Status> $statuses none for every status
   * @param ?string $sort a field or spine key; null is best match first under a search, else newest first
   * @param list<string> $searchIn the field keys the reader reads, which alone a search is asked of
   */
  public function __construct(
    public string $type,
    public array $filters = [],
    public array $statuses = [],
    public ?Locale $locale = null,
    public ?string $sort = null,
    public bool $descending = false,
    public string $search = '',
    public int $page = 1,
    public int $perPage = self::PER_PAGE,
    public array $searchIn = [],
    public bool $trashed = false,
    private Json $json = new Json(),
  ) {
    /* A page past the last integer's offset would warn on the cast, which a host showing warnings prints. */
    if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE || $page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
      throw MalformedValue::named('page of at most ' . self::MAX_PER_PAGE, $page . '/' . $perPage);
    }
  }

  /** @param array<string, list<string>> $filters */
  public function filteredBy(array $filters): self
  {
    return clone($this, ['filters' => $filters]);
  }

  /* The trash instead of the holdings, never both: a list is one or the other. */
  public function inTrash(): self
  {
    return clone($this, ['trashed' => true]);
  }

  public function where(TypeDefinition $type): string
  {
    $clauses = ['e.type = :type',
      'e.deleted_at is ' . ($this->trashed ? 'not null' : 'null'),
      '(:locale is null or ' . Collisions::IN_LOCALE . ')',
      "(:statuses = '[]' or case when " . self::PUBLIC . " then 'published'"
        . " when e.status <> 'published' then e.status end in (select value from json_each(:statuses)))",
      '(:match is null or e.id in (select entity_id from (' . Search::hits() . ')))'];
    foreach (array_keys($this->filters) as $index => $key) {
      $clauses[] = $this->filterClause($type, $key, $index);
    }

    return implode(' and ', $clauses);
  }

  /* Under a search the word table leads, by `cross join`, which SQLite never reorders:
     led by `entity`, it would run the match once per row, 2.7 s at 10 000 rows. */
  public function from(): string
  {
    return $this->search === '' ? 'entity e'
      : '(' . Search::hits() . ') s cross join entity e on e.id = s.entity_id';
  }

  public function page(TypeDefinition $type): string
  {
    $direction = $this->descending ? ' desc' : '';
    $primary = match (true) {
      $this->sort !== null => $this->sortExpression($type, $this->sort) . $direction . ' nulls last, ',
      $this->search !== '' => 's.hits desc, ',
      default => '',
    };
    $tie = $primary === '' ? ' desc' : $direction;

    return sprintf(' order by %se.created_at%s, e.id%s limit %d offset %d', $primary, $tie, $tie,
      $this->perPage, ($this->page - 1) * $this->perPage);
  }

  /** @return array<string, string> what `where` names, less the clock, locale and search */
  public function bindings(TypeDefinition $type, SchemaOrg $schema, SortKeys $keys): array
  {
    $statuses = array_map(fn (Status $status): string => $status->value, $this->statuses);
    $bindings = ['type' => $type->url(), 'statuses' => $this->json->encode($statuses)];
    foreach (array_keys($this->filters) as $index => $key) {
      if (in_array($key, self::COLUMNS, true)) {
        $bindings["f$index"] = $this->json->encode($this->filters[$key]);
        continue;
      }
      $field = $this->field($type, $key);
      $named = (string) $schema->parameterName($field->key);
      $typed = fn (string $value): string|int|float|bool|array => match (true) {
        in_array($field->kind, self::RANGED, true) => $this->range($field, $value, $named),
        str_contains($value, '..') && !$field->cardinality->isEdge()
          => throw MalformedValue::named('number or date for a range on ' . $named, $value),
        SortKeys::keys($field) || SortKeys::listed($field) => $keys->key($value),
        default => $this->typed($field, $value, $named),
      };
      $bindings["f$index"] = $this->json->encode(array_map($typed, $this->filters[$key]));
      $bindings["k$index"] = $this->address($field, $schema);
    }

    return $bindings;
  }

  /** @return array<string, string> what `page` names */
  public function sortBindings(TypeDefinition $type, SchemaOrg $schema): array
  {
    if ($this->sort === null || in_array($this->sort, self::SPINE, true)) {
      return [];
    }

    return ['sort' => $this->address($this->field($type, $this->sort), $schema)];
  }

  private function filterClause(TypeDefinition $type, string $key, int $index): string
  {
    $values = "(select value from json_each(:f$index))";
    if (in_array($key, self::COLUMNS, true)) {
      return "e.$key in $values";
    }
    $field = $this->field($type, $key);

    return match (true) {
      $field->cardinality->isEdge() => 'exists (select 1 from relation r'
        . " where r.subject_id = e.id and r.predicate = :k$index"
        . " and r.object_id in $values)",
      SortKeys::keys($field) => "exists (select 1 from json_each(:f$index) v"
        . " where substr({$this->keyed(":k$index")}, 1, length(v.value)) = v.value)",
      SortKeys::listed($field) => "exists (select 1 from json_each({$this->keyed(":k$index")}) x,"
        . " json_each(:f$index) v where substr(x.value, 1, length(v.value)) = v.value)",
      in_array($field->kind, self::RANGED, true) => $this->rangeClause($field, $index),
      $field->repeatable => "exists (select 1 from json_each(e.props, :k$index) x where "
        . $this->cased($field, 'x.value') . " in $values)",
      default => $this->cased($field, "json_extract(e.props, :k$index)") . " in $values",
    };
  }

  private function cased(FieldDefinition $field, string $value): string
  {
    return $field->kind === FieldKind::Link ? "lower($value)" : $value;
  }

  /* The key at the bound path in the route's words where `sort_keys` keeps some
     (`SortKeys`), else the authored one; the admin's reading names no locale. */
  private function keyed(string $path): string
  {
    return "coalesce(json_extract(e.sort_keys, '$." . LocalisedText::KEY . ".' || :locale"
      . " || substr($path, 2)), json_extract(e.sort_keys, $path))";
  }

  private function rangeClause(FieldDefinition $field, int $index): string
  {
    $each = $field->repeatable ? "json_each(e.props, :k$index) x, " : '';
    $value = $field->repeatable ? 'x.value' : "json_extract(e.props, :k$index)";

    return "exists (select 1 from {$each}json_each(:f$index) v where "
      . $this->end($field, $value, 0, '>=') . ' and ' . $this->end($field, $value, 1, '<=') . ')';
  }

  /* One end of a range, open where the bound is null. A date compares cut to
     the bound's length, so `2026` holds every day of that year. */
  private function end(FieldDefinition $field, string $value, int $end, string $operator): string
  {
    $bound = "json_extract(v.value, '$[$end]')";
    $compared = $field->kind === FieldKind::Number ? $value : "substr($value, 1, length($bound))";

    return "($bound is null or $compared $operator $bound)";
  }

  /* `from..to`, one end left empty for open; a bare value is both ends, and no
     end at all asks nothing, so it is refused rather than matching every row.
     @return list<string|int|float|null> */
  private function range(FieldDefinition $field, string $value, string $named): array
  {
    $ends = str_contains($value, '..') ? explode('..', $value, 2) : [$value, $value];
    if ($ends === ['', '']) {
      throw MalformedValue::named('bound for ' . $named, $value);
    }

    return array_map(fn (string $end): string|int|float|null => $end === '' ? null : match (true) {
      $field->kind === FieldKind::Number => is_numeric($end) && is_finite($end + 0) ? $end + 0
        : throw MalformedValue::named('number for ' . $named, $end),
      preg_match(self::SHAPES[$field->kind->value], $end) === 1 => $end,
      default => throw MalformedValue::named($field->kind->value . ' for ' . $named, $end),
    }, $ends);
  }

  /* Many values sort by the first, which is the one a list shows, and an edge by
     its target's slug, which its name made, so no label is computed per row. */
  private function sortExpression(TypeDefinition $type, string $key): string
  {
    if (in_array($key, self::SPINE, true)) {
      return 'e.' . $key;
    }
    $field = $this->field($type, $key);

    return match (true) {
      $field->cardinality->isEdge() => '(select t.slug from relation r'
        . ' join entity t on t.id = r.object_id'
        . ' where r.subject_id = e.id and r.predicate = :sort'
        . ' order by r.position, r.id limit 1)',
      SortKeys::keys($field) => $this->keyed(':sort'),
      SortKeys::listed($field) => "json_extract({$this->keyed(':sort')}, '$[0]')",
      $field->repeatable => "json_extract(e.props, :sort || '[0]')",
      default => 'json_extract(e.props, :sort)',
    };
  }

  private function field(TypeDefinition $type, string $key): FieldDefinition
  {
    return $type->field($key) ?? throw MalformedValue::named('field of ' . $type->name, $key);
  }

  private function address(FieldDefinition $field, SchemaOrg $schema): string
  {
    return $field->cardinality->isEdge()
      ? (string) $schema->propertyName($field->key)
      : '$.' . $field->key;
  }

  /* The refusal names the field as the address spells it, which is how the caller wrote it. */
  private function typed(FieldDefinition $field, string $value, string $named): string|int|float|bool
  {
    return match (true) {
      $field->kind === FieldKind::Link => strtolower($value),
      $field->kind === FieldKind::Boolean => in_array($value, ['true', 'false'], true)
        ? $value === 'true'
        : throw MalformedValue::named('boolean for ' . $named, $value),
      default => $value,
    };
  }

  /* Where `PUBLIC` and `Status::isPublicAt` read one status in one window
     apart, each case as `status from..to`: the feed's lists and its records ask
     the two, and a draft must be neither's.
     @return list<string> */
  public static function publicDisagreements(): array
  {
    $disagreeing = [];
    $now = '2026-06-01T12:00:00Z';
    $windows = [[null, null], ['2026-01-01T00:00:00Z', null], ['2026-12-01T00:00:00Z', null],
      [null, '2026-01-01T00:00:00Z'], ['2026-01-01T00:00:00Z', '2026-12-01T00:00:00Z']];
    $statement = (new \PDO('sqlite::memory:'))->prepare('select ' . self::PUBLIC
      . ' from (select :status as status, :publish_from as publish_from, :publish_to as publish_to) e');
    foreach (Status::cases() as $status) {
      foreach ($windows as [$from, $to]) {
        $statement->execute(['status' => $status->value, 'publish_from' => $from, 'publish_to' => $to,
          'now' => $now]);
        if ((bool) $statement->fetchColumn() !== $status->isPublicAt(new \DateTimeImmutable($now), $from, $to)) {
          $disagreeing[] = $status->value . ' ' . $from . '..' . $to;
        }
      }
    }

    return $disagreeing;
  }
}
