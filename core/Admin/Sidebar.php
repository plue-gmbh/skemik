<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\GrantRepository;
use Feed\PublicFilter;
use Storage\Collection;
use Storage\CollectionField;
use Storage\Counts;
use Storage\CollectionStore;
use Storage\Sites;
use Storage\TypeCount;
use Support\Clock;
use Support\Words;

/* The collection list on every admin screen. Empty collections sort below the
   filled ones, each group by name, so a new install's three working collections are
   not pushed out of view by the empty ones; an empty one a live key holds a rule in
   stands before the others, since it is set up and waits for its first record. A mark that does not apply is absent
   rather than drawn grey, so a list of thirty-one is read by what stands out. The
   serving mark is read from the clock, so a collection whose only record goes live
   next week has none until then; the grant mark names the keys that hold a rule
   there, as the collection's badge does, and only a live key marks. Outside the read scope the count
   is what passes `Feed\PublicFilter`, and a type with no public state is not
   listed, since its reading there can never fill. Marks and counts are one grouped
   query each, not one per entry. */
final readonly class Sidebar
{
  public function __construct(
    private CollectionStore $collections,
    private Counts $counts,
    private Sites $sites,
    private Authorizer $authorizer,
    private PublicFilter $filter,
    private GrantRepository $grants,
    private Clock $clock,
    private Words $words,
  ) {}

  /** @return list<SidebarEntry> filled collections first, then the empty ones a key holds, each group by name */
  public function entries(Account $account): array
  {
    $counts = $this->counts->byType();
    $holders = $this->grants->holders($this->clock->now()->format(Clock::STORED));
    $entries = [];
    foreach ($this->collections->all() as $collection) {
      $entry = $this->entry($account, $collection, $counts[$collection->type] ?? null);
      if ($entry !== null) {
        $entries[] = $entry->heldBy($holders[$collection->slug->value] ?? []);
      }
    }
    $collator = new \Collator($this->words->language);
    $group = fn (SidebarEntry $entry): int => $entry->count > 0 ? 0 : ($entry->holders === [] ? 2 : 1);
    usort($entries, fn (SidebarEntry $one, SidebarEntry $other): int
      => $group($one) <=> $group($other) ?: (int) $collator->compare($one->name, $other->name));

    return $entries;
  }

  /** The organisation's own sites, which the header draws where it has room and the drawer
      where it has not, a site's and a page's only while the feed lets their type's `url`
      out: everybody is shown them, and a colleague reading `WebSite` as a stranger must
      not be shown what a stranger is not.
      @return array<string, string> a host by URL */
  public function sites(): array
  {
    $out = array_filter($this->collections->all(), fn (Collection $held): bool
      => in_array($held->type, [Sites::WEB_SITE, Sites::WEB_PAGE], true)
      && array_any($this->filter->fields($held), fn (CollectionField $field): bool => $field->key === 'url'));

    return $out === [] ? [] : $this->sites->linked(array_values(array_map(
      fn (Collection $held): string => $held->type, $out)));
  }

  private function entry(Account $account, Collection $collection, ?TypeCount $count): ?SidebarEntry
  {
    $public = $this->filter->passesType($collection->type);
    $outside = $this->authorizer->readsAsPublic($account, $collection->type);
    if ($outside && !$public) {
      return null;
    }
    $rows = $outside ? ($count?->public ?? 0) : ($count?->rows ?? 0);

    return new SidebarEntry(SidebarEntry::glyphOf($collection->type), $collection->name,
      $collection->slug->value, $rows, $public && ($count?->public ?? 0) > 0);
  }
}
