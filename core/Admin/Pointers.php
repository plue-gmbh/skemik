<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\EntityId;
use Domain\Locale;
use Domain\LocalisedText;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\RelationRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\Words;

/* The record screen's Points here, per type and field: the newest ten drawn, the rest one row opening that
   collection filtered to the record, since a café is pointed at by every reservation it took in ten years.
   Every record counts once it passes the reader's scope (`Rows`), read without its fields until drawn, so a
   count never discloses what the record itself would not. A work's translations pointing with one field are
   one row, named in the locale the screen reads, else by the one the others translate. */
final readonly class Pointers
{
  private const DRAWN = 10;

  public function __construct(
    private Showing $showing,
    private RelationRepository $relations,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private SettingStore $settings,
    private Sheet $sheet,
    private Rows $rows,
    private Profile $profile,
    private SchemaOrg $schema,
    private Clock $clock,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  /** @return list<Pointer> each named in the words of the locale the screen reads */
  public function of(Account $account, Entity $entity, Locale $in): array
  {
    $collections = $this->collections->all();
    $target = Collection::SLUGS[$entity->typeName()] . '/' . $entity->slug->value;
    $pointers = [];
    foreach ($this->grouped($account, $entity) as $key => $roots) {
      [$url, $predicate] = explode(' ', $key, 2);
      $type = $this->profile->byType($url);
      $holder = array_find($collections, fn (Collection $collection): bool => $collection->type === $url);
      $group = new PointerGroup($type, $predicate, $this->field($type, $predicate), $holder, $target, $in);
      array_push($pointers, ...$this->drawn($group, $roots));
    }

    return $pointers;
  }

  /** The readable records pointing, by type and predicate, then by work, newest first.
      @return array<string, array<string, list<EntityId>>> */
  private function grouped(Account $account, Entity $entity): array
  {
    $predicates = [];
    foreach ($this->showing->edges($entity) as $edge) {
      $predicates[$edge->subject->value][$edge->predicate] = true;
    }
    $groups = [];
    $now = $this->clock->now()->format(Clock::STORED);
    foreach ($this->relations->pointing(array_map(strval(...), array_keys($predicates)), $now) as $record) {
      if (!$this->rows->readableAs($account, $record->type, $record->public)) {
        continue;
      }
      foreach (array_keys($predicates[$record->id->value]) as $predicate) {
        $groups[$record->type . ' ' . $predicate][$record->root][] = $record->id;
      }
    }

    return $groups;
  }

  /** @param array<string, list<EntityId>> $roots by work, newest first
      @return list<Pointer> */
  private function drawn(PointerGroup $group, array $roots): array
  {
    $drawn = array_map(fn (array $ids): Pointer => $this->pointer($group, $this->entities->byIds($ids)),
      array_values(array_slice($roots, 0, self::DRAWN)));
    $rest = array_sum(array_map(count(...), array_slice($roots, self::DRAWN)));

    return $rest === 0 ? $drawn : [...$drawn, new Pointer(SidebarEntry::glyphOf($group->type->url()),
      $this->words->get('record.pointers_more', ['n' => $this->words->number($rest)]), '',
      $this->words->get('type.' . $group->type->name), $group->field, $group->filtered(), more: $rest)];
  }

  /** @param list<Entity> $members one record, or the translations of one work */
  private function pointer(PointerGroup $group, array $members): Pointer
  {
    $subject = array_find($members, fn (Entity $member): bool => $member->locale->tag === $group->in->tag)
      ?? array_find($members, fn (Entity $member): bool => $member->translationOf === null) ?? $members[0];
    $named = (new LocalisedText($group->type))->shown($subject, $group->in);
    $records = $this->locales($members, $group->holder);
    $words = $records === [] && $group->type->locale->localises()
      ? $this->worded($subject, $group->holder, $group->type) : [];

    return new Pointer(SidebarEntry::glyphOf($subject->type), $this->label->of($named, $group->type),
      $this->sheet->state($subject), $this->words->get('type_one.' . $group->type->name), $group->field,
      $group->holder === null ? null : Rows::address($group->holder, $subject), $records ?: $words, $words !== []);
  }

  private function field(TypeDefinition $type, string $predicate): string
  {
    return array_find($type->edges(),
      fn (FieldDefinition $edge): bool => $this->schema->propertyName($edge->key) === $predicate)?->label
      ?? $predicate;
  }

  /** A labelled thing's languages, the authored one and each holding words of its own, every one opening
      the record on its words, since what points here reads it in each of them.
      @return array<string, string> */
  private function worded(Entity $subject, ?Collection $holder, TypeDefinition $type): array
  {
    $held = (new LocalisedText($type))->locales($subject);
    if ($holder === null || $held === []) {
      return [];
    }
    $locales = [];
    foreach ($this->settings->locales() as $locale) {
      $own = $locale->tag === $subject->locale->tag;
      if ($own || in_array($locale->tag, $held, true)) {
        $locales[$locale->tag] = Rows::address($holder, $subject, $own ? '' : 'words=' . $locale->tag);
      }
    }

    return $locales;
  }

  /** Each translation's address in the install's order of its locales.
      @param list<Entity> $members
      @return array<string, string> */
  private function locales(array $members, ?Collection $holder): array
  {
    if (count($members) < 2 || $holder === null) {
      return [];
    }
    $order = array_map(fn (Locale $locale): string => $locale->tag, $this->settings->locales());
    usort($members, fn (Entity $one, Entity $other): int => array_search($one->locale->tag, $order, true)
      <=> array_search($other->locale->tag, $order, true));
    $locales = [];
    foreach ($members as $member) {
      $locales[$member->locale->tag] = Rows::address($holder, $member);
    }

    return $locales;
  }
}
