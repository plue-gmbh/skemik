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
use Profile\FieldKind;
use Profile\LocaleMode;
use Profile\Profile;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\Counts;
use Storage\EntityRepository;
use Storage\Query;
use Storage\SettingStore;
use Support\Words;

/* The collection's address read as a query, and the choices the bar offers.
   Beside the feed's parameters, parsed by `Feed\FeedRequest` as for `/feed`, the
   admin reads its own: `status` and `locale`, and on the record screen `in` and
   `words`. A filter without a status shows drafts too, since inside the holding
   filtering to what one is working on must not hide it. Outside the read scope
   the query goes through `Feed\PublicFilter`, like every read there. Language
   leaves the labelled things, since one answers on every locale route. A
   reference in the address is read by `Feed\Targets` as the feed reads it, and
   the bar offers the targets the rows hold, since those are closed sets the
   house wrote: through a term field every term, and through a single reference
   its targets while they stay few, since a select of every customer is no
   select. Nothing says a group was left out: the address still filters, and the
   search reaches a target's name (`Storage\Search`). */
final readonly class QueryBar
{
  public const OWN = ['status', 'locale', 'in', 'words', 'view'];
  private const FEED = ['sort', 'q', 'page', 'per_page'];
  private const FEW = 20;

  public function __construct(
    private FeedRequest $parameters,
    private PublicFilter $filter,
    private Authorizer $authorizer,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private Counts $counts,
    private SettingStore $settings,
    private Rows $rows,
    private Profile $profile,
    private Terms $terms,
    private Targets $addresses,
    private Words $words,
  ) {}

  /* Null where nothing asked for could pass the reader's filter. */
  public function query(Account $account, Request $request, Collection $collection): ?Query
  {
    $public = $this->authorizer->readsAsPublic($account, $collection->type);
    $locale = $this->locale($request->query('locale'));
    $parsed = $this->parameters->query($request->without(...self::OWN),
      $collection->carrying($this->rows->fields($account, $collection)), $locale);
    $filters = $locale !== null && $this->profile->byType($collection->type)->locale->forks()
      ? [...$parsed->filters, 'locale' => [$locale->tag]] : $parsed->filters;
    $status = $request->query('status');
    $statuses = $status === null ? [] : [$this->status($collection, $status)];
    $query = $this->addresses->filtered(new Query($parsed->type, $filters, $statuses, $parsed->locale,
      $parsed->sort, $parsed->descending, $parsed->search, $parsed->page, $parsed->perPage, $parsed->searchIn),
      [...($parsed->locale === null ? [] : [$parsed->locale]), ...$this->settings->locales()],
      fn (Entity $target): bool => $this->rows->readable($account, $target))
      ->through($this->rows->readings($account, $this->collections->all()));

    return $public ? $this->filter->query($query, $collection) : $query;
  }

  /* The whole reading, which a filtered count is said against. */
  public function total(Account $account, Collection $collection): int
  {
    $all = new Query($collection->type);
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

  /** Each narrowing parameter in words, the field's name and its values, a closed set's by
      their word and any other as the address spells it.
      @param list<Column> $columns
      @return list<Narrowed> */
  public function narrowed(Request $request, array $columns): array
  {
    $narrowed = [];
    foreach ($this->narrowing($request) as $parameter => $value) {
      $column = array_find($columns, fn (Column $column): bool => $column->parameter === $parameter);
      $values = array_map(fn (string $each): string => $this->valued($column, $parameter, $each),
        explode(',', $value));
      $without = http_build_query(array_diff_key($request->query, [$parameter => true, 'page' => true]));
      $narrowed[] = new Narrowed($this->words->get('query.narrowed', ['field' => match ($parameter) {
        'status' => $this->words->get('query.status_group'),
        'locale' => $this->words->get('query.locale_group'),
        default => $column->label ?? $parameter,
      }, 'values' => implode(', ', $values)]), $request->path . ($without === '' ? '' : '?' . $without));
    }

    return $narrowed;
  }

  private function valued(?Column $column, string $parameter, string $value): string
  {
    $status = $parameter === 'status' ? Status::tryFrom($value) : null;

    return match (true) {
      $status !== null => $this->words->get('status.' . $status->value),
      $column !== null && $column->field->options !== [] => $this->terms->word($column->field, $value,
        $this->settings->locales()[0]),
      default => $value,
    };
  }

  /**
   * @param list<Column> $columns
   * @return list<Filter> in the order the select groups them
   */
  public function filters(Account $account, Collection $collection, array $columns): array
  {
    $outside = $this->authorizer->readsAsPublic($account, $collection->type);
    $filters = $outside ? [] : $this->statuses($collection);
    $labelled = $this->profile->byType($collection->type)->locale === LocaleMode::Localise;
    $locales = $labelled ? [] : $this->counts->locales($collection->type, $outside);
    $group = $this->words->get('query.locale_group');
    foreach (count($locales) > 1 ? $locales : [] as $tag) {
      $filters[] = new Filter($group, 'locale', $tag, $tag);
    }
    $pointed = $this->pointed($account, $collection, $columns, $outside);
    foreach ($columns as $column) {
      $filters = [...$filters, ...match (true) {
        $column->field->kind === FieldKind::Select => $this->options($collection, $column),
        isset($pointed[$column->parameter]) => $this->targets($collection, $column, $pointed[$column->parameter]),
        default => [],
      }];
    }

    return $filters;
  }

  /** The records the rows point at, by the field's parameter, which is the edge's predicate, each
      in the reader's scope as a chip of it would be: every term a term field holds, and a single
      reference's targets while they stay few.
      @param list<Column> $columns
      @return array<string, list<Entity>> */
  private function pointed(Account $account, Collection $collection, array $columns, bool $outside): array
  {
    $predicates = fn (Cardinality $of): array => array_values(array_map(
      fn (Column $column): string => $column->parameter,
      array_filter($columns, fn (Column $column): bool => $column->field->cardinality === $of
        && $column->field->kind === FieldKind::Ref && ($of === Cardinality::T || !$column->field->holdsMany()))));
    $terms = $this->counts->targets($collection->type, $predicates(Cardinality::T), $outside, null);
    $references = array_filter(
      $this->counts->targets($collection->type, $predicates(Cardinality::E), $outside, self::FEW + 1),
      fn (array $ids): bool => count($ids) <= self::FEW);

    return array_map(fn (array $ids): array => array_values(array_filter($this->entities->byIds($ids),
      fn (Entity $target): bool => $this->rows->readable($account, $target))), [...$terms, ...$references]);
  }

  /** Each target worded as its chip is, in the house's first language, and where two in a group
      share a word, two runs of one course, each with its slug beside it, so the two choices differ.
      @param list<Entity> $targets
      @return list<Filter> */
  private function targets(Collection $collection, Column $column, array $targets): array
  {
    $group = $this->group($collection, $column);
    $locale = $this->settings->locales()[0];
    $collections = $this->collections->all();
    $words = array_map(fn (Entity $target): string => $this->rows->named($target, $locale), $targets);
    $repeated = array_filter(array_count_values($words), fn (int $times): bool => $times > 1);
    $filters = [];
    foreach ($targets as $index => $target) {
      $value = $this->spelt($column, $target, $collections);
      if ($value !== null) {
        $filters[] = new Filter($group, $column->parameter, $value,
          $words[$index] . (isset($repeated[$words[$index]]) ? ' · ' . $target->slug->value : ''));
      }
    }

    return $filters;
  }

  /** A target as the feed spells it and `Feed\Targets` reads it back: a term by its code and a
      record by its address; null for one of a type no collection holds, which has no address.
      @param list<Collection> $collections */
  private function spelt(Column $column, Entity $target, array $collections): ?string
  {
    if ($column->field->cardinality === Cardinality::T) {
      return $target->props->string('term_code');
    }
    $holder = array_find($collections, fn (Collection $each): bool => $each->type === $target->type);

    return $holder === null ? null : $holder->slug->value . '/' . $target->slug->value;
  }

  /** @return list<Filter> */
  private function statuses(Collection $collection): array
  {
    $group = $this->words->get('query.status_group');

    return array_map(fn (Status $status): Filter => new Filter($group, 'status', $status->value,
      $this->words->get('status.' . $status->value)), Status::heldBy($this->profile->byType($collection->type)));
  }

  /** The profile's options, and none of the world's list: a bar offering every
      country is no bar, and `?addressCountry=DE` in the address filters as well.
      @return list<Filter> */
  private function options(Collection $collection, Column $column): array
  {
    $group = $this->group($collection, $column);
    $locale = $this->settings->locales()[0];

    return array_map(fn (string $token): Filter => new Filter($group, $column->parameter, $token,
      $this->terms->word($column->field, $token, $locale)), $column->field->options);
  }

  /* A field's group names its type beside it, since a field may be called Status too. */
  private function group(Collection $collection, Column $column): string
  {
    return $this->words->get('query.group', ['field' => $column->label, 'of' => basename($collection->type)]);
  }

  private function status(Collection $collection, string $value): Status
  {
    $status = Status::tryFrom($value);

    return $status !== null && in_array($status, Status::heldBy($this->profile->byType($collection->type)), true)
      ? $status : throw MalformedValue::named('status of ' . $collection->name, $value);
  }

  /* A locale the house does not hold is refused by name, as an unknown field is. */
  private function locale(?string $tag): ?Locale
  {
    if ($tag === null) {
      return null;
    }

    return array_find($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw MalformedValue::named('locale of this house', $tag);
  }
}
