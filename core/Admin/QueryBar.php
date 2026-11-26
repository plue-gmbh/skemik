<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Domain\Entity;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Status;
use Feed\FeedRequest;
use Feed\PublicFilter;
use Feed\Targets;
use Http\Request;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\Range;
use Storage\Search;
use Storage\SettingStore;
use Support\Clock;
use Support\Words;

/* The collection's address read as a query, and what narrows it in words; the
   choices the bar offers are `QueryChoices`'. Beside the feed's parameters,
   parsed by `Feed\FeedRequest` as for `/feed`, the admin reads its own: `status`,
   any of several as a field's values are, and `locale`, and on the record screen
   `in` and `words`. A filter without a
   status shows drafts too, since inside the holding filtering to what one is
   working on must not hide it. A collection of works lists each once, in the
   install's earliest locale it holds, unless `locale` asks for one or for each.
   Outside the read scope the query goes through `Feed\PublicFilter`, like every
   read there. A reference in the address is read by `Feed\Targets` as the feed
   reads it. */
final readonly class QueryBar
{
  public const OWN = ['status', 'locale', 'in', 'words', 'view'];
  public const EACH = 'each';
  private const FEED = ['sort', 'q', 'page', 'per_page'];
  /* The days a date's filter offers, as the address spells them, each to its word. The days past count today, as
     what happened this morning did; the next days start tomorrow, today having its own choice. What is upcoming
     has no end, as a house plans its events months ahead, and counts today, whose evening is still to come. */
  public const DAYS = ['today' => 'query.today', 'today-1' => 'query.yesterday',
    'today-6..today' => 'query.last_7', 'today-29..today' => 'query.last_30', 'today+1' => 'query.tomorrow',
    'today+1..today+7' => 'query.next_7', 'today..' => 'query.upcoming'];
  /* The relative dates said in words, the month among them, which is typed into the address and offered nowhere. */
  private const SAID = [...self::DAYS, 'month' => 'query.month'];

  public function __construct(
    private FeedRequest $parameters,
    private PublicFilter $filter,
    private Authorizer $authorizer,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private SettingStore $settings,
    private Rows $rows,
    private Profile $profile,
    private Terms $terms,
    private Targets $addresses,
    private Words $words,
    private SchemaOrg $schema,
    private Clock $clock,
  ) {}

  /* Null where nothing asked for could pass the reader's filter. */
  public function query(Account $account, Request $request, Collection $collection): ?Query
  {
    $public = $this->authorizer->readsAsPublic($account, $collection->type);
    $request = $this->dated($request, $collection);
    $locale = $this->locale($request->query('locale'));
    $parsed = $this->parameters->query($request->without(...self::OWN),
      $collection->carrying($this->rows->fields($account, $collection)), $locale);
    $filters = $locale !== null && $this->profile->byType($collection->type)->locale->forks()
      ? [...$parsed->filters, 'locale' => [$locale->tag]] : $parsed->filters;
    $status = $request->query('status');
    $statuses = $status === null ? [] : array_map(fn (string $one): Status => $this->status($collection, $one),
      explode(',', $status));
    $query = $this->addresses->filtered(new Query($parsed->type, $filters, $statuses, $parsed->locale,
      $parsed->sort, $parsed->descending, Search::bounded($parsed->search), $parsed->page, $parsed->perPage,
      $parsed->searchIn, grouped: $this->grouped($request, $collection)),
      [...($parsed->locale === null ? [] : [$parsed->locale]), ...$this->settings->locales()],
      fn (Entity $target): bool => $this->rows->readable($account, $target))
      ->through($this->rows->readings($account, $this->collections->all()));

    return $public ? $this->filter->query($query, $collection) : $query;
  }

  /* The address with each relative date as the day or month it names today, which is how the feed spells it. */
  public function dated(Request $request, Collection $collection): Request
  {
    $today = new \DateTimeImmutable($this->clock->today());
    foreach ($this->profile->byType($collection->type)->fields() as $field) {
      $name = (string) $this->schema->parameterName($field->key);
      $asked = $request->query($name);
      if ($asked !== null && in_array($field->kind, Range::DATES, true)) {
        $request = $request->with($name, Range::dated($asked, $today));
      }
    }

    return $request;
  }

  /** The whole reading, which a filtered count is said against, as the page groups it.
      @param list<string> $grouped */
  public function total(Account $account, Collection $collection, array $grouped): int
  {
    $all = new Query($collection->type, grouped: $grouped);
    $query = $this->authorizer->readsAsPublic($account, $collection->type)
      ? $this->filter->query($all, $collection)
      : $all;

    return $query === null ? 0 : $this->entities->count($query);
  }

  /** What narrows the list, as opposed to ordering or paging it.
      @return array<string, string> */
  public function narrowing(Request $request): array
  {
    return array_diff_key($request->query, array_flip([...self::FEED, 'in', 'words', 'view']));
  }

  /** Each value that narrows, a chip to take away by itself: under the word and the group of the choice
      that offers it, a kind of file standing for its formats, else alone where it says what it is, a
      record by its name among them, its field's name then the chip's tooltip, and behind that name where
      it does not, as a saved question's title has it (`titled`). A record this reader may not read stays
      the address it was asked by.
      @param list<Column> $columns
      @param list<Filter> $filters the choices the bar offers, unjoined
      @return list<Narrowed> */
  public function narrowed(Account $account, Request $request, array $columns, array $filters): array
  {
    $narrowed = [];
    foreach (array_keys($this->narrowing($request)) as $parameter) {
      $column = array_find($columns, fn (Column $column): bool => $column->parameter === $parameter);
      $field = $this->fieldNamed($parameter, $column);
      $left = $this->asked($request, $parameter);
      foreach ($filters as $filter) {
        $offered = explode(',', $filter->value);
        if ($filter->parameter === $parameter && array_diff($offered, $left) === []) {
          $narrowed[] = new Narrowed($filter->word, $this->without($request, $parameter, $offered),
            $filter->group, true);
          $left = array_diff($left, $offered);
        }
      }
      foreach ($left as $one) {
        $word = $this->recorded($account, $column?->field, $one) ?? $this->valued($column?->field, $parameter, $one);
        $narrowed[] = new Narrowed($word, $this->without($request, $parameter, [$one]), $field, $word !== $one);
      }
    }

    return $narrowed;
  }

  /** @return list<string> the values a parameter is narrowed to, none where it is not asked */
  private function asked(Request $request, string $parameter): array
  {
    $asked = (string) $request->query($parameter);

    return $asked === '' ? [] : explode(',', $asked);
  }

  private function fieldNamed(string $parameter, ?Column $column): string
  {
    return match ($parameter) {
      'status' => $this->words->get('query.status_group'),
      'locale' => $this->words->get('query.locale_group'),
      default => $column->label ?? $parameter,
    };
  }

  /** The address without these values of a parameter, the others where they stood, from its first page.
      @param list<string> $values */
  private function without(Request $request, string $parameter, array $values): string
  {
    $kept = array_diff($this->asked($request, $parameter), $values);
    $query = array_diff_key($request->query, ['page' => true, ...($kept === [] ? [$parameter => true] : [])]);
    if ($kept !== []) {
      $query[$parameter] = implode(',', $kept);
    }

    return $request->path . ($query === [] ? '' : '?' . http_build_query($query));
  }

  /** The narrowing as a saved question's title says it, each part in the reader's words: a value alone
      where it says what it is, a status, a closed set's word, a day or a record's name, and behind its
      field's name where it does not, a number or a text. A search stands as typed.
      @return ?list<string> null where it names a record this reader does not read, since a slug is a name */
  public function titled(Account $account, Request $request, Collection $collection): ?array
  {
    $fields = $this->profile->byType($collection->type)->fields();
    $parts = [];
    foreach ($this->narrowing($request) as $parameter => $asked) {
      $field = $this->meant($fields, $parameter);
      $values = array_map(fn (string $each): ?string => $field?->cardinality->isEdge() === true
        ? $this->recorded($account, $field, $each) : $this->valued($field, $parameter, $each), explode(',', $asked));
      if (in_array(null, $values, true)) {
        return null;
      }
      $parts[] = $field === null || $values !== explode(',', $asked) ? implode(' · ', $values)
        : $this->words->get('query.narrowed', ['field' => $field->label, 'values' => implode(' · ', $values)]);
    }

    return [...$parts, ...array_filter([(string) $request->query('q')])];
  }

  /** The type's own field before a value object's key of the same name, as the feed reads it: an event's
      `url` is the event's, not its virtual location's.
      @param list<FieldDefinition> $fields */
  private function meant(array $fields, string $parameter): ?FieldDefinition
  {
    $named = array_filter($fields, fn (FieldDefinition $each): bool
      => $this->schema->parameterName($each->key) === $parameter);

    return array_find($named, fn (FieldDefinition $each): bool => $each->valueObject === null) ?? array_first($named);
  }

  /* A record the address names, a term by its code, by what its chip calls it; null where this reader may
     not read it, and where the field names no record. One that no longer stands is said as the address spells
     it, so a question on it is seen and deleted, and only to whoever reads the type its collection names, a
     slug being a name. */
  private function recorded(Account $account, ?FieldDefinition $field, string $value): ?string
  {
    if ($field === null || !$field->cardinality->isEdge()) {
      return null;
    }
    $locales = $this->settings->locales();
    $record = $field->cardinality === Cardinality::T ? $this->addresses->coded($value)[0] ?? null
      : array_find(array_map(fn (Locale $locale): ?Entity => $this->addresses->at($value, $locale), $locales),
        fn (?Entity $found): bool => $found !== null);
    if ($record === null) {
      preg_match('#([a-z0-9-]+)/[^/]+$#', $value, $at);
      $type = $field->cardinality === Cardinality::T ? 'DefinedTerm'
        : array_search($at[1] ?? '', Collection::SLUGS, true);

      return $type === false || $this->authorizer->readsAsPublic($account, $type) ? null : $value;
    }

    return $this->rows->readable($account, $record) ? $this->rows->named($record, $locales[0]) : null;
  }

  private function valued(?FieldDefinition $field, string $parameter, string $value): string
  {
    $status = $parameter === 'status' ? Status::tryFrom($value) : null;

    return match (true) {
      $status !== null => $this->words->get('status.' . $status->value),
      $parameter === 'locale' && $value === self::EACH => $this->words->get('query.locale_each'),
      $field !== null && $field->options !== [] => $this->terms->word($field, $value,
        new Locale($this->words->language)),
      $field !== null && in_array($field->kind, Range::DATES, true) && isset(self::SAID[$value])
        => $this->words->get(self::SAID[$value]),
      default => $value,
    };
  }

  private function status(Collection $collection, string $value): Status
  {
    $status = Status::tryFrom($value);

    return $status !== null && in_array($status, Status::heldBy($this->profile->byType($collection->type)), true)
      ? $status : throw MalformedValue::named('status of ' . $collection->name, $value);
  }

  /** The install's locales where a work is listed once, none where every record is its own row.
      @return list<string> */
  private function grouped(Request $request, Collection $collection): array
  {
    $locales = $this->settings->locales();
    $once = $request->query('locale') === null && count($locales) > 1
      && $this->profile->byType($collection->type)->locale->forks();

    return $once ? array_map(fn (Locale $locale): string => $locale->tag, $locales) : [];
  }

  /* A locale the install does not hold is refused by name, as an unknown field is. */
  private function locale(?string $tag): ?Locale
  {
    if ($tag === null || $tag === self::EACH) {
      return null;
    }

    return array_find($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw MalformedValue::named('locale of this install', $tag);
  }
}
