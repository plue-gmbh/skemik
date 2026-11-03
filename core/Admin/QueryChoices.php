<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Domain\Entity;
use Domain\Status;
use Domain\TranslationGroup;
use Profile\Cardinality;
use Profile\FieldKind;
use Profile\LocaleMode;
use Profile\MediaKind;
use Profile\Profile;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\Counts;
use Storage\EntityRepository;
use Storage\SettingStore;
use Support\Words;

/* The choices the query bar offers, each spelled as the address takes it (`QueryBar`).
   Only what the rows hold is offered, so no choice shows an empty view: a status,
   a language, a select's options, the library's files by kind, through a term
   field every term, and through any other reference its targets while they stay
   few, since a select of every customer is no select. Language leaves the
   labelled things, since one answers on every locale route. Nothing says a group
   was left out: the address still filters, and the search reaches a target's
   name (`Storage\Search`). */
final readonly class QueryChoices
{
  private const FEW = 20;
  private const FORMAT = 'encoding_format';

  public function __construct(
    private Authorizer $authorizer,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private Counts $counts,
    private SettingStore $settings,
    private Rows $rows,
    private Profile $profile,
    private Terms $terms,
    private Words $words,
  ) {}

  /**
   * @param list<Column> $columns
   * @return list<Filter> in the order the select groups them
   */
  public function filters(Account $account, Collection $collection, array $columns): array
  {
    $outside = $this->authorizer->readsAsPublic($account, $collection->type);
    $held = $this->held($collection, $columns, $outside);
    $filters = $outside ? [] : $this->statuses($collection, $held['status'] ?? []);
    $labelled = $this->profile->byType($collection->type)->locale === LocaleMode::Localise;
    $locales = $labelled ? [] : $held['locale'] ?? [];
    $group = $this->words->get('query.locale_group');
    foreach (count($locales) > 1 ? $locales : [] as $tag) {
      $filters[] = new Filter($group, 'locale', $tag, $tag);
    }
    $pointed = $this->pointed($account, $collection, $columns, $outside);
    foreach ($columns as $column) {
      $filters = [...$filters, ...match (true) {
        $column->field->kind === FieldKind::Select => $this->options($collection, $column,
          $held[$column->field->key] ?? []),
        isset($pointed[$column->parameter]) => $this->targets($collection, $column, $pointed[$column->parameter]),
        $column->field->key === self::FORMAT => $this->kinds($column, $held[self::FORMAT] ?? []),
        default => [],
      }];
    }

    return $filters;
  }

  /** What the rows hold of the status, the language, each select column and the library's formats.
      @param list<Column> $columns
      @return array<string, list<string>> by `status`, `locale` and each asked field's key */
  private function held(Collection $collection, array $columns, bool $outside): array
  {
    $selects = array_filter($columns, fn (Column $column): bool => $column->field->kind === FieldKind::Select
      || $column->field->key === self::FORMAT);

    return $this->counts->held($collection->type,
      array_values(array_map(fn (Column $column): string => $column->field->key, $selects)), $outside);
  }

  /** The records the rows point at, by the field's parameter, which is the edge's predicate, each
      in the reader's scope as a chip of it would be: every term a term field holds, and any other
      reference's targets while they stay few, one record pointing at several as at one, and a
      work once for all its translations, since filtering by one finds edges to each (`Feed\Targets`).
      @param list<Column> $columns
      @return array<string, list<Entity>> */
  private function pointed(Account $account, Collection $collection, array $columns, bool $outside): array
  {
    $predicates = fn (Cardinality $of): array => array_values(array_map(
      fn (Column $column): string => $column->parameter,
      array_filter($columns, fn (Column $column): bool => $column->field->cardinality === $of
        && in_array($column->field->kind, [FieldKind::Ref, FieldKind::Refs], true))));
    $terms = $this->counts->targets($collection->type, $predicates(Cardinality::T), $outside, null);
    $references = $this->counts->targets($collection->type, $predicates(Cardinality::E), $outside, self::FEW);

    $locales = $this->settings->locales();

    return array_map(fn (array $ids): array => TranslationGroup::once(array_values(array_filter(
      $this->entities->byIds($ids), fn (Entity $target): bool => $this->rows->readable($account, $target))),
      $locales), [...$terms, ...$references]);
  }

  /** Each target worded as its chip is, in the house's first language and ordered by that word,
      and where two in a group share a word, two runs of one course, each with its slug beside it,
      so the two choices differ.
      @param list<Entity> $targets
      @return list<Filter> */
  private function targets(Collection $collection, Column $column, array $targets): array
  {
    $group = $this->group($collection, $column);
    $locale = $this->settings->locales()[0];
    $collator = new \Collator($locale->tag);
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
    usort($filters, fn (Filter $one, Filter $other): int => (int) $collator->compare($one->word, $other->word));

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

  /** @param list<string> $held the statuses rows show, as a filter reads them
      @return list<Filter> */
  private function statuses(Collection $collection, array $held): array
  {
    $group = $this->words->get('query.status_group');
    $statuses = array_filter(Status::heldBy($this->profile->byType($collection->type)),
      fn (Status $status): bool => in_array($status->value, $held, true));

    return array_values(array_map(fn (Status $status): Filter => new Filter($group, 'status', $status->value,
      $this->words->get('status.' . $status->value)), $statuses));
  }

  /** The profile's options that rows hold, in the profile's order, and none of the world's
      list: a bar offering every country is no bar, and `?addressCountry=DE` filters as well.
      @param list<string> $held
      @return list<Filter> */
  private function options(Collection $collection, Column $column, array $held): array
  {
    $group = $this->group($collection, $column);
    $locale = $this->settings->locales()[0];
    $options = array_intersect($column->field->options, $held);

    return array_values(array_map(fn (string $token): Filter => new Filter($group, $column->parameter, $token,
      $this->terms->word($column->field, $token, $locale)), $options));
  }

  /** Each kind of the library's files its rows hold, as a house asks for its images and never its
      PNGs, spelled as the formats the kind's filter takes (`Profile\MediaKind`), under a group of its
      own, since the field's name says MIME type and the choices are none.
      @param list<string> $held
      @return list<Filter> */
  private function kinds(Column $column, array $held): array
  {
    $group = $this->words->get('media.kind');

    return array_map(fn (MediaKind $kind): Filter => new Filter($group, $column->parameter,
      implode(',', $kind->formats()), $this->words->get('media.kind.' . $kind->value)), MediaKind::finding($held));
  }

  /* A field called what the Status or Language group is called, as four types call
     theirs, names its collection beside it, so the two groups differ. */
  private function group(Collection $collection, Column $column): string
  {
    $own = [$this->words->get('query.status_group'), $this->words->get('query.locale_group')];

    return in_array($column->label, $own, true)
      ? $this->words->get('query.group', ['field' => $column->label, 'of' => $collection->name])
      : $column->label;
  }
}
