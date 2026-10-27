<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\EntityId;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Status;
use Profile\Profile;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\EntityRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\Words;

/* What the spine dialog shows (`partials/spine-dialog`), handed to the island as
   JSON: once per collection the states its type holds, the house's locales and
   whether the type's second language is a second record; per record its spine as the dialog
   edits it, the window in the house's wall time, the locales its own words are
   kept in where the type has both mechanisms, and who changed it last. The
   record screen's Languages card reads a work's translations from here, each
   passing the reader's scope first. */
final readonly class SpineDialog
{
  private const WORDS = ['spine.open', 'spine.slug', 'spine.address', 'spine.moved', 'spine.status',
    'spine.publish_from', 'spine.publish_to', 'spine.window_order', 'spine.locale', 'spine.worded'];

  public function __construct(
    private EntityRepository $entities,
    private SettingStore $settings,
    private Rows $rows,
    private Sheet $sheet,
    private Profile $profile,
    private ActivityLog $log,
    private Clock $clock,
    private Moments $moments,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  /** @param list<SheetRow> $rows */
  public function data(Collection $collection, array $rows): string
  {
    $type = $this->profile->byType($collection->type);
    $holds = Status::heldBy($type);
    $names = $this->log->lastActorNames(array_map(fn (SheetRow $row): EntityId => $row->entity->id, $rows));
    $spine = fn (SheetRow $row): array => $this->spine($collection, $row, $names[$row->entity->id->value] ?? null);

    return (new Json())->encode(['collection' => $collection->slug->value,
      'public' => in_array(Status::Published, $holds, true), 'forks' => $type->locale->forks(),
      'statuses' => array_map(fn (Status $status): array => ['value' => $status->value,
        'word' => $this->words->get('status.' . $status->value)], $holds),
      'locales' => array_map(fn (Locale $locale): string => $locale->tag, $this->settings->locales()),
      'words' => (object) array_combine(self::WORDS, array_map(fn (string $key): string => $this->words->get($key,
        ['locales' => '{locales}']), self::WORDS)),
      'rows' => (object) array_combine(array_map(fn (SheetRow $row): string => $row->entity->id->value, $rows),
        array_map($spine, $rows))]);
  }

  /**
   * One record's spine as the dialog edits it; a save answers with it too.
   *
   * @return array<string, string|int|list<string>|list<array<string, string|bool|null>>|null>
   */
  public function row(Collection $collection, SheetRow $row): array
  {
    return $this->spine($collection, $row, $this->log->lastActorName($row->entity->id));
  }

  /** @return array<string, string|int|list<string>|list<array<string, string|bool|null>>|null> */
  private function spine(Collection $collection, SheetRow $row, ?string $lastBy): array
  {
    $entity = $row->entity;
    $wall = fn (?string $stored): ?string => $stored === null ? null : $this->clock->render($stored);

    return ['slug' => $entity->slug->value, 'status' => $entity->status->value, 'state' => $row->state,
      'publish_from' => $wall($entity->publishFrom), 'publish_to' => $wall($entity->publishTo),
      'locale' => $entity->locale->tag, 'address' => Rows::address($collection, $entity),
      'worded' => (new LocalisedText($this->profile->byType($entity->type)))->locales($entity),
      'own' => $this->own($collection, $row), 'footer' => $this->footer($entity, $lastBy)];
  }

  /* Whether a table row shows its record's language: on a work, since its record
     is its locale's alone, and only where the house writes in more than one. */
  public function multilingual(Collection $collection): bool
  {
    return $this->profile->byType($collection->type)->locale->forks() && count($this->settings->locales()) > 1;
  }

  /* A work's house locales, each with its member or, where none, the records
     that could be linked; null on a type whose second language is not a second record.
     @return ?list<Translation> */
  public function translations(Account $account, Collection $collection, Entity $entity): ?array
  {
    if (!$this->profile->byType($entity->type)->locale->forks()) {
      return null;
    }
    $group = $this->entities->group($entity);

    return array_map(function (Locale $locale) use ($account, $collection, $entity, $group): Translation {
      $member = $group->inLocale($locale);
      if ($member === null) {
        $linkable = array_filter($this->entities->ungrouped($entity->type, $locale),
          fn (Entity $one): bool => $this->rows->readable($account, $one));

        return new Translation($locale->tag, candidates: array_values(array_map(
          fn (Entity $one): Translation => $this->translation($collection, $one), $linkable)));
      }

      return $this->rows->readable($account, $member) ? $this->translation($collection, $member, $entity)
        : new Translation($locale->tag);
    }, $this->settings->locales());
  }

  private function translation(Collection $collection, Entity $member, ?Entity $viewed = null): Translation
  {
    $label = $this->label->of($member, $this->profile->byType($member->type));

    return new Translation($member->locale->tag, $member, $label, $this->sheet->state($member),
      Rows::address($collection, $member), age: $viewed === null ? '' : $this->age($member, $viewed));
  }

  /* Read from the row's timestamp, which a status change and a restore move too,
     so it claims nothing about which language holds the newer words. Within the
     hour it says nothing, a translation made right after its original. */
  private function age(Entity $member, Entity $viewed): string
  {
    $minutes = intdiv((new \DateTimeImmutable($member->updatedAt))->getTimestamp()
      - (new \DateTimeImmutable($viewed->updatedAt))->getTimestamp(), 60);
    $side = $minutes < 0 ? 'older' : 'newer';
    $minutes = abs($minutes);
    if ($minutes < 60) {
      return '';
    }

    return $minutes < 1440 ? $this->words->count('age', 'hours_' . $side, intdiv($minutes, 60))
      : $this->words->count('age', 'days_' . $side, intdiv($minutes, 1440));
  }

  /* Where the type has no public state, the sentence standing in for the missing
     states names the field that says how the record stands. */
  private function own(Collection $collection, SheetRow $row): ?string
  {
    $type = $this->profile->byType($collection->type);
    if (in_array(Status::Published, Status::heldBy($type), true)) {
      return null;
    }
    $key = array_find(Sheet::OWN_STATUS, fn (string $key): bool => $type->field($key) !== null);
    if ($key === null) {
      return $this->words->get('spine.unpublic_data', ['type' => $type->name]);
    }

    return $this->words->get('spine.unpublic', ['type' => $type->name,
      'field' => (string) $type->field($key)?->label, 'word' => $row->own ?? '—']);
  }

  private function footer(Entity $entity, ?string $lastBy): string
  {
    $values = ['version' => (string) $entity->version, 'date' => $this->moments->day($entity->updatedAt),
      'name' => (string) $lastBy];

    return $this->words->get($lastBy === null ? 'spine.footer_unnamed' : 'spine.footer', $values);
  }
}
