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
   filled ones, each group by name, so a new house's three working collections are
   not pushed out of view by the empty ones. A mark that does not apply is absent
   rather than drawn grey, so a list of thirty is read by what stands out. The
   serving mark is read from the clock, so a collection whose only record goes live
   next week has none until then; the grant mark names who writes, the thing a
   reader can act on, and only a live grant marks. Outside the read scope the count
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

  /** @return list<SidebarEntry> filled collections first, each group by name */
  public function entries(Account $account): array
  {
    $counts = $this->counts->byType();
    $writers = $this->grants->writers($this->clock->now()->format(Clock::STORED));
    $filled = [];
    $empty = [];
    foreach ($this->collections->all() as $collection) {
      $entry = $this->entry($account, $collection, $counts[$collection->type] ?? null)
        ?->writtenBy($writers[$collection->slug->value] ?? []);
      if ($entry !== null && $entry->count > 0) {
        $filled[] = $entry;
      } elseif ($entry !== null) {
        $empty[] = $entry;
      }
    }

    $collator = new \Collator($this->words->language);
    $byName = fn (SidebarEntry $one, SidebarEntry $other): int => (int) $collator->compare($one->name, $other->name);
    usort($filled, $byName);
    usort($empty, $byName);

    return [...$filled, ...$empty];
  }

  /* The house's own sites, which the header draws where it has room and the drawer
     where it has not, and only while the feed lets their `url` out: everybody is
     shown them, and a colleague reading `WebSite` as a stranger must not be shown
     what a stranger is not.
     @return array<string, string> a host by URL */
  public function sites(): array
  {
    $site = array_find($this->collections->all(), fn (Collection $held): bool => $held->type === Sites::WEB_SITE);
    $out = $site !== null && array_any($this->filter->fields($site),
      fn (CollectionField $field): bool => $field->key === 'url');

    return $out ? $this->sites->linked() : [];
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
