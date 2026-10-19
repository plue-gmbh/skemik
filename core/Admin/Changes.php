<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\TokenRepository;
use Domain\ActionType;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Storage\Activity;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\HouseObject;
use Support\Clock;
use Support\Words;

/* The home screen's Last changed, whose contract is the header of
   `view/partials/home-changes.php`, and the lines of the log screen's
   (`view/pages/activity.php`). The log is read newest first and only as far
   as seven records the reader may open, so a colleague scoped to one type walks
   further back than the owner and never learns from a gap what stood there. A
   record in the trash is listed for whoever may take it back out, opening that
   trash, since the record screen does not open it; a purged one is gone, and
   only the log screen lists it, by the type its purge kept. An account, a key
   or a language is `Admin\HouseChanges`'s. */
final readonly class Changes
{
  private const SHOWN = 7;

  /* What a row keeps that is no former value: the trash's way and a redaction's note. */
  private const NOTES = ['deleted_at' => true, 'redacted' => true, 'activity' => true, 'type' => true];

  public function __construct(
    private ActivityLog $log,
    private EntityRepository $entities,
    private TokenRepository $tokens,
    private CollectionStore $collections,
    private Sheet $sheet,
    private Rows $rows,
    private HouseChanges $house,
    private Clock $clock,
    private Moments $moments,
    private Words $words,
  ) {}

  /** @return list<Change> */
  public function latest(Account $account): array
  {
    $collections = $this->collections->all();
    $changes = [];
    foreach ($this->log->latest() as $activity) {
      $change = $this->line($account, $activity, $collections);
      $changes = $change === null ? $changes : [...$changes, $change];
      if (count($changes) === self::SHOWN) {
        break;
      }
    }

    return $changes;
  }

  /**
   * Null where the reader could not open what it acted on, or it is gone.
   * @param list<Collection> $collections
   */
  public function line(Account $account, Activity $activity, array $collections): ?Change
  {
    if ($activity->target instanceof HouseObject) {
      return $this->houseChange($account, $activity, $activity->target);
    }
    try {
      $entity = $this->entities->find($activity->target);
    } catch (EntityNotFound) {
      return null;
    }
    $collection = array_find($collections, fn (Collection $held): bool => $held->type === $entity->type);
    if ($collection === null || !$this->rows->readable($account, $entity)) {
      return null;
    }
    $trashed = $entity->deletedAt !== null;
    if ($trashed && !$this->rows->opensTrash($account, $collection)) {
      return null;
    }
    $row = $this->row($account, $entity);

    return new Change($row, $row->label,
      $trashed ? '/trash/' . $collection->slug->value : Rows::address($collection, $entity),
      $this->what($entity->typeName(), $activity), ...$this->said($activity));
  }

  /* A record purged since, or an account whose person is gone, as the log
     screen lists it: the purge by the type it kept, every earlier row of the
     record as a record's. */
  public function gone(Activity $activity): Change
  {
    $what = $activity->target instanceof HouseObject ? $this->house->what($activity->target, $activity)
      : $this->what((string) $activity->result?->string('type'), $activity);

    return new Change(null, $this->words->get('change.gone'), null, $what, ...$this->said($activity),
      glyph: 'gone');
  }

  private function houseChange(Account $account, Activity $activity, HouseObject $object): ?Change
  {
    $label = $this->house->label($account, $object, $activity);

    return $label === null ? null : new Change(null, $label, $this->house->href($object),
      $this->house->what($object, $activity), ...$this->said($activity),
      glyph: $this->house->glyph($object));
  }

  /** @return array{who: string, state: ?string, by: ?string, ago: string, stamp: string, when: string,
      kept: list<string>, activity: ?string, byKey: bool} */
  private function said(Activity $activity): array
  {
    $kept = $activity->target instanceof HouseObject ? [] : $this->kept($activity);

    return [...$this->actor($activity), 'by' => $activity->person ?? $activity->token,
      'ago' => $this->ago($activity->at), 'stamp' => $this->moments->time($activity->at),
      'when' => $this->clock->render($activity->at), 'kept' => $kept,
      'activity' => $kept === [] ? null : $activity->id, 'byKey' => $activity->token !== null];
  }

  /* What a redaction would take: a former address, a number held once, an edge
     a purge took, each as it went and what it became.
     @return list<string> */
  private function kept(Activity $activity): array
  {
    $kept = [];
    foreach (array_diff_key($activity->result?->document() ?? [], self::NOTES) as $pair) {
      $end = fn (string $which): string => is_string($pair[$which] ?? null) && $pair[$which] !== ''
        ? $pair[$which] : '–';
      $kept = is_array($pair) ? [...$kept, $end('from') . ' → ' . $end('to')] : $kept;
    }

    return $kept;
  }

  /* No type names a record gone before the row its purge wrote. */
  private function what(string $type, Activity $activity): string
  {
    return $this->words->get('change.what', ['done' => $this->words->get($this->done($activity)),
      'type' => $this->words->get($type === '' ? 'change.record' : 'type_one.' . $type)]);
  }

  /* As a glance wants it, and past a week the day itself. */
  private function ago(string $at): string
  {
    $minutes = intdiv($this->clock->now()->getTimestamp() - new \DateTimeImmutable($at)->getTimestamp(), 60);

    return match (true) {
      $minutes < 1 => $this->words->get('change.just_now'),
      $minutes < 60 => $this->words->get('change.minutes', ['n' => (string) $minutes]),
      $minutes < 1440 => $this->words->get('change.hours', ['n' => (string) intdiv($minutes, 60)]),
      $minutes < 2880 => $this->words->get('change.day'),
      $minutes < 10080 => $this->words->get('change.days', ['n' => (string) intdiv($minutes, 1440)]),
      default => $this->moments->day($at),
    };
  }

  /* A record in the trash says so where its state would stand, as the trash's own notice does. */
  private function row(Account $account, Entity $entity): SheetRow
  {
    $row = $this->sheet->rows($account, [], [$entity])[0];

    return $entity->deletedAt === null ? $row
      : new SheetRow($entity, $row->label, 'trashed', $row->reach, [], changed: $row->changed);
  }

  /* Read from what the log kept: the trash and its undo are updates of
     `deleted_at`, a new address one of the slug, a redaction says which. */
  private function done(Activity $activity): string
  {
    $moved = $activity->result?->document()['deleted_at'] ?? null;
    $redacted = $activity->result?->string('redacted');

    return match (true) {
      $activity->action === ActionType::Create => 'change.created',
      $activity->action === ActionType::Delete => 'change.purged',
      $redacted !== null => $redacted === 'actor_name' ? 'change.redacted_name' : 'change.redacted_result',
      is_array($moved) => ($moved['to'] ?? null) === null ? 'change.restored' : 'change.trashed',
      $activity->result?->has('slug') === true => 'change.moved',
      default => 'change.changed',
    };
  }

  /* Unknown only for a name redacted, which a person's current name must not
     undo; the install is nobody's and says so. The state stands apart from the
     name, so a screen can link the name alone.
     @return array{who: string, state: ?string} */
  private function actor(Activity $activity): array
  {
    if ($activity->person === null && $activity->token === null) {
      return ['who' => $this->words->get('change.install'), 'state' => null];
    }
    if ($activity->name === null) {
      return ['who' => $this->words->get('change.unknown'), 'state' => null];
    }

    return $activity->token !== null ? $this->token($activity->token, $activity->name)
      : $this->person((string) $activity->person, $activity->name);
  }

  /* By the name the person holds now, so a colleague who changed theirs is not
     two people, and by the kept one once the record is gone.
     @return array{who: string, state: ?string} */
  private function person(string $id, string $kept): array
  {
    try {
      $person = $this->entities->find(new EntityId($id));
    } catch (EntityNotFound) {
      $person = null;
    }
    if ($person === null || $person->deletedAt !== null) {
      return ['who' => $kept, 'state' => $this->words->get('change.removed')];
    }
    $name = trim((string) $person->props->string('name'));

    return ['who' => $name === '' ? $kept : $name, 'state' => null];
  }

  /** @return array{who: string, state: ?string} */
  private function token(string $id, string $kept): array
  {
    try {
      $token = $this->tokens->find($id);
    } catch (EntityNotFound) {
      return ['who' => $kept, 'state' => null];
    }
    $key = match (true) {
      $token->revokedAt !== null => 'change.revoked',
      $token->expiresAt <= $this->clock->now()->format(Clock::STORED) => 'change.expired',
      default => null,
    };

    return ['who' => $kept, 'state' => $key === null ? null : $this->words->get($key)];
  }
}
