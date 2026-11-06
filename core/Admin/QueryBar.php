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
use Profile\Profile;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\SettingStore;
use Support\Words;

/* The collection's address read as a query, and what narrows it in words; the
   choices the bar offers are `QueryChoices`'. Beside the feed's parameters,
   parsed by `Feed\FeedRequest` as for `/feed`, the admin reads its own: `status`
   and `locale`, and on the record screen `in` and `words`. A filter without a
   status shows drafts too, since inside the holding filtering to what one is
   working on must not hide it. Outside the read scope the query goes through
   `Feed\PublicFilter`, like every read there. A reference in the address is read
   by `Feed\Targets` as the feed reads it. */
final readonly class QueryBar
{
  public const OWN = ['status', 'locale', 'in', 'words', 'view'];
  private const FEED = ['sort', 'q', 'page', 'per_page'];

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

  private function status(Collection $collection, string $value): Status
  {
    $status = Status::tryFrom($value);

    return $status !== null && in_array($status, Status::heldBy($this->profile->byType($collection->type)), true)
      ? $status : throw MalformedValue::named('status of ' . $collection->name, $value);
  }

  /* A locale the install does not hold is refused by name, as an unknown field is. */
  private function locale(?string $tag): ?Locale
  {
    if ($tag === null) {
      return null;
    }

    return array_find($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw MalformedValue::named('locale of this install', $tag);
  }
}
