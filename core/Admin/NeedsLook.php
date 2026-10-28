<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\GrantRepository;
use Domain\EntityId;
use Domain\Exceptions\UnknownType;
use Domain\Reason;
use Domain\Status;
use Domain\Violation;
use Profile\Profile;
use Profile\Promises;
use Profile\Reach;
use Profile\Validator;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Oversights;
use Storage\RelationRepository;
use Storage\SettingStore;
use Support\Clock;
use Update\Backup;
use Update\FileCheck;
use Update\UpdateCheck;

/* The home screen's Needs a look, whose contract is the header of
   `view/partials/home-needs-look.php`. Only the types the account reads as staff
   are counted: outside its read scope a colleague reads what a stranger reads,
   which holds no draft, and what it does hold they cannot mend. A missing
   required field is read, not scanned for: the `Validator` refuses a save
   without one, so such a row arrives only when a release gives a type a † after
   rows exist, which the holdings check reports. Of the rows it listed only those
   changed since are checked again, so a filled-in record leaves at once and a
   visit never loads the thousand a release left, which the next slice drops. */
final readonly class NeedsLook
{
  /* The types whose draft is meant to be finished. A person, an organisation, a
     branch or a place is held as a draft by design, a customer or a member being
     one, as are a file marked for the house, a term and what somebody did. */
  private const FINISHED = ['Article', 'WebPage', 'WebSite', 'HowTo', 'Course', 'Dataset', 'Book', 'Review',
    'Comment', 'Product', 'Service', 'MemberProgram', 'Brand', 'Offer', 'Event', 'ItemList', 'JobPosting',
    'SoftwareApplication', 'Question'];
  /* What a till or a booking service writes nobody began, so it is counted by
     how it stands, `Promises::STANDING`, and not as a draft: a till writes an
     order already delivered. Open until delivered, cancelled or returned, and
     until confirmed or cancelled. */
  private const OPEN = ['Order' => ['OrderPaymentDue', 'OrderProcessing', 'OrderInTransit'],
    'Reservation' => ['ReservationPending', 'ReservationHold']];
  private const MONTH = 'P30D';
  /* A draft is old once its week is out, however lately it was edited: begun
     and not finished is what the finding asks after. */
  private const WEEK = 'P7D';

  public function __construct(
    private SettingStore $settings,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private CollectionStore $collections,
    private Validator $validator,
    private Authorizer $authorizer,
    private Oversights $oversights,
    private GrantRepository $grants,
    private Profile $profile,
    private Backup $backup,
    private FileCheck $files,
    private UpdateCheck $updates,
    private Clock $clock,
  ) {}

  /** @return list<Finding> in the order of `Overlooked`, none with nothing to count */
  public function findings(Account $account): array
  {
    $collections = $this->collections->all();
    $read = $this->read($account, $collections);
    $public = array_values(array_filter($read, fn (string $type): bool
      => $this->profile->byType($type)->reach === Reach::Public));
    $finished = array_values(array_filter($public, fn (string $type): bool
      => in_array(basename($type), self::FINISHED, true)));
    $media = $this->profile->byType('MediaObject')->url();

    return array_values(array_filter([
      $this->opened(Overlooked::Unread, $this->unread($read), $collections),
      $this->counted(Overlooked::Orders, $this->standing('Order', $read), $collections,
        'orderStatus=' . implode(',', self::OPEN['Order'])),
      $this->counted(Overlooked::Reservations, $this->standing('Reservation', $read), $collections,
        'reservationStatus=' . implode(',', self::OPEN['Reservation'])),
      $this->counted(Overlooked::Draft, $this->oversights->inState(Status::Draft, $finished, $this->ago()),
        $collections, 'status=draft'),
      $this->counted(Overlooked::Review, $this->oversights->inState(Status::Review, $read), $collections,
        'status=review'),
      $this->opened(Overlooked::Required, $this->unfilled($read), $collections),
      $this->counted(Overlooked::NoAlt, in_array($media, $read, true) ? $this->oversights->withoutAlt($media)
        : [], $collections, 'encodingFormat=image'),
      $this->opened(Overlooked::Dangling, $this->oversights->pointingIntoTrash($read), $collections),
      $account->can(Capability::ManageHouse) ? $this->reported() : null,
      $account->can(Capability::RunBackup) ? $this->snapshot() : null,
      $account->can(Capability::ManageHouse) ? $this->shipped() : null,
      $account->can(Capability::RunUpdate) ? $this->update() : null,
      $account->can(Capability::ManageTokens) ? $this->expiring($collections) : null,
    ]));
  }

  /**
   * @param array<string, int> $byType most first
   * @param list<Collection> $collections
   */
  private function counted(Overlooked $look, array $byType, array $collections, string $filter): ?Finding
  {
    if ($byType === []) {
      return null;
    }
    $first = self::holder((string) array_key_first($byType), $collections);

    return new Finding($look, array_sum($byType), '/c/' . $first->slug->value . '?' . $filter,
      SidebarEntry::glyphOf($first->type), $first->name, count($byType) - 1);
  }

  /* No filter says which records these are, so it opens the first of them,
     and the next once that one is mended.
     @param array<string, list<EntityId>> $byType most first
     @param list<Collection> $collections */
  private function opened(Overlooked $look, array $byType, array $collections): ?Finding
  {
    if ($byType === []) {
      return null;
    }
    $type = (string) array_key_first($byType);
    $first = self::holder($type, $collections);

    return new Finding($look, array_sum(array_map(count(...), $byType)),
      Rows::address($first, $this->entities->find($byType[$type][0])), SidebarEntry::glyphOf($type),
      $first->name, count($byType) - 1);
  }

  /* What a contact form wrote lands as a draft and stays one, so a message is
     new until somebody sets the `dateRead` schema.org gives it, by hand or by
     key, which no filter can name: the line opens the oldest instead.
     @param list<string> $read the type URLs counted
     @return array<string, list<EntityId>> by type URL */
  private function unread(array $read): array
  {
    $message = $this->profile->byType('Message')->url();

    return in_array($message, $read, true) ? $this->oversights->unread($message) : [];
  }

  /**
   * @param list<string> $read the type URLs counted
   * @return array<string, int> by type URL
   */
  private function standing(string $name, array $read): array
  {
    $type = $this->profile->byType($name)->url();

    return in_array($type, $read, true)
      ? $this->oversights->standing($type, Promises::STANDING[$name], self::OPEN[$name]) : [];
  }

  /**
   * @param list<string> $read the type URLs counted
   * @return array<string, list<EntityId>> by type URL, most first
   */
  private function unfilled(array $read): array
  {
    $report = $this->settings->holdingsReport();
    $listed = array_filter(array_map(fn (Violation $violation): ?EntityId
      => $violation->reason === Reason::Required ? $violation->record : null, $report?->violations ?? []));
    $ids = [];
    $since = $report?->at->format(Clock::STORED) ?? '';
    foreach ($this->oversights->listed(array_values($listed), $read, $since) as $type => $records) {
      $still = array_filter($records, fn (bool $changed, string $id): bool
        => !$changed || $this->missing(new EntityId($id)), ARRAY_FILTER_USE_BOTH);
      if ($still !== []) {
        $ids[$type] = array_map(fn (string $id): EntityId => new EntityId($id), array_keys($still));
      }
    }
    uasort($ids, fn (array $one, array $other): int => count($other) <=> count($one));

    return $ids;
  }

  /* Read again, not taken as mended: a restore changes a row without a save. */
  private function missing(EntityId $id): bool
  {
    $entity = $this->entities->find($id);
    $found = $this->validator->check($this->profile->byType($entity->type), $entity->props->document(),
      $this->relations->outbound($id));

    return array_any($found, fn (Violation $violation): bool => $violation->reason === Reason::Required);
  }

  /* What the report holds beyond a missing required field, which has its own line,
     counted as the last slice left it, since reading a row again is the check's
     work, and for the owner, whose checks screen it opens. */
  private function reported(): ?Finding
  {
    $found = array_filter($this->settings->holdingsReport()?->violations ?? [],
      fn (Violation $violation): bool => $violation->reason !== Reason::Required);

    return $found === [] ? null : new Finding(Overlooked::Reported, count($found), '/settings/checks', 'alert', '', 0);
  }

  /* A daily snapshot that failed, or a window shrunk to make room, as the last
     run left it: a line here and not an absence. */
  private function snapshot(): ?Finding
  {
    $report = $this->backup->report();
    $look = match (true) {
      $report === null => null,
      $report->failed !== null => Overlooked::BackupFailed,
      $report->shrunk => Overlooked::BackupShrunk,
      default => null,
    };

    return $look === null ? null : new Finding($look, 1, '/settings/backups', 'alert', '', 0);
  }

  /* Files that differ from the release, as the daily run last compared them, the
     first named and opening nothing, since the Settings card's row names them all. */
  private function shipped(): ?Finding
  {
    $differing = $this->files->report()?->differing() ?? [];

    return $differing === [] ? null
      : new Finding(Overlooked::Files, count($differing), null, 'alert', $differing[0], count($differing) - 1);
  }

  /* A release that cannot be installed by itself, the version as the note, so
     the owner learns the copy by hand is theirs from the screen everybody opens. */
  private function update(): ?Finding
  {
    $refused = $this->updates->report()->refused;

    return $refused === null ? null
      : new Finding(Overlooked::Update, 1, '/settings/update', 'alert', 'Skemik ' . $refused, 0);
  }

  /* An integration that stops on a known date is one somebody meant to renew.
     @param list<Collection> $collections */
  private function expiring(array $collections): ?Finding
  {
    $now = $this->clock->now();
    $keys = $this->grants->expiring($now->format(Clock::STORED),
      $now->add(new \DateInterval(self::MONTH))->format(Clock::STORED));
    $bySlug = array_count_values($keys);
    arsort($bySlug);
    $first = array_find($collections, fn (Collection $held): bool
      => $held->slug->value === (string) array_key_first($bySlug));

    return $first === null ? null : new Finding(Overlooked::Expiring, count($keys), '/c/' . $first->slug->value,
      SidebarEntry::glyphOf($first->type), $first->name, count($bySlug) - 1);
  }

  /**
   * @param list<Collection> $collections
   * @return list<string> the type URLs the account reads as staff
   */
  private function read(Account $account, array $collections): array
  {
    return array_values(array_filter(array_map(fn (Collection $held): string => $held->type, $collections),
      fn (string $type): bool => !$this->authorizer->readsAsPublic($account, $type)));
  }

  private function ago(): string
  {
    return $this->clock->now()->sub(new \DateInterval(self::WEEK))->format(Clock::STORED);
  }

  /** @param list<Collection> $collections */
  private static function holder(string $type, array $collections): Collection
  {
    return array_find($collections, fn (Collection $held): bool => $held->type === $type)
      ?? throw UnknownType::named($type);
  }
}
