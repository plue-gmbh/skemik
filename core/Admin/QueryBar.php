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
   the bar offers none: a record is found by what it points at through the
   search, which reaches a target's name (`Storage\Search`), and the one exact
   target stays the address's. */
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

  /* What narrows the list, as opposed to ordering or paging it.
     @return array<string, string> */
  public function narrowing(Request $request): array
  {
    return array_diff_key($request->query, array_flip([...self::FEED, 'in', 'words', 'view']));
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
    foreach ($columns as $column) {
      $filters = [...$filters, ...($column->field->kind === FieldKind::Select
        ? $this->options($collection, $column) : [])];
    }

    return $filters;
  }

  /** @return list<Filter> */
  private function statuses(Collection $collection): array
  {
    $group = $this->words->get('query.status_group');

    return array_map(fn (Status $status): Filter => new Filter($group, 'status', $status->value,
      $this->words->get('status.' . $status->value)), Status::heldBy($this->profile->byType($collection->type)));
  }

  /* The profile's options, and none of the world's list: a bar offering every
     country is no bar, and `?addressCountry=DE` in the address filters as well.
     @return list<Filter> */
  private function options(Collection $collection, Column $column): array
  {
    $group = $this->words->get('query.group',
      ['field' => $column->label, 'of' => basename($collection->type)]);
    $locale = $this->settings->locales()[0];

    return array_map(fn (string $token): Filter => new Filter($group, $column->parameter, $token,
      $this->terms->word($column->field, $token, $locale)), $column->field->options);
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
