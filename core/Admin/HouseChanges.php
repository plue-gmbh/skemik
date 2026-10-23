<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Capability;
use Auth\TokenRepository;
use Domain\ActionType;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Props;
use Domain\Slug;
use Profile\Profile;
use Storage\Activity;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\HouseItem;
use Storage\HouseObject;
use Support\Words;

/* Last changed's lines for an account, a key, a language or a collection,
   beside the records' (`Admin\Changes`). Each is shown only to whoever manages
   it, as the card it lives on is, and opens that card. An account is named by
   its person's current name and a key by its name; one whose person was purged
   is gone, as a purged record is. What an edit changed is read from what the
   log kept, never an address, so a changed login says only that it changed. A
   collection is one a key arranged (`Ingest\ArrangeController`) or a colleague
   did (`FieldController`), shown to whoever manages fields and reads the type
   as staff, opening the collection, the fields let out named first, since
   that is the disclosure. A dump
   (`ExportController`) is shown to whoever reads the log, named by the types it
   ran under, since who took it is the line's own. */
final readonly class HouseChanges
{
  /* What an arrangement did to its fields, in the order the log keeps: let out, withheld, added, removed. */
  private const ARRANGED = ['let_out' => 'change.let_out', 'withheld' => 'change.withheld',
    'added' => 'change.fields_added', 'removed' => 'change.fields_removed'];

  public function __construct(
    private EntityRepository $entities,
    private TokenRepository $tokens,
    private CollectionStore $collections,
    private Profile $profile,
    private Words $words,
  ) {}

  /* Null where the reader does not manage it, or its person is gone. */
  public function label(Account $account, HouseObject $object, Activity $activity): ?string
  {
    if (!$account->can(match ($object->item) {
      HouseItem::Account => Capability::ManageAccounts,
      HouseItem::Key => Capability::ManageTokens,
      HouseItem::Locale => Capability::ManageHouse,
      HouseItem::Collection => Capability::ManageFields,
      HouseItem::Dump => Capability::ReadLog,
    })) {
      return null;
    }

    return match ($object->item) {
      HouseItem::Account => $this->person((string) $activity->result?->string('person')),
      HouseItem::Dump => $this->scope($activity->result ?? new Props([])),
      HouseItem::Key => $this->tokens->find($object->id)->label,
      HouseItem::Locale => $object->id,
      HouseItem::Collection => $this->collection($account, $object)?->name,
    };
  }

  /* A collection's as the sidebar draws it, and a dump's as the export screen does. */
  public function glyph(HouseObject $object): string
  {
    $type = $object->item === HouseItem::Collection ? $this->collection(null, $object)?->type : null;

    return match ($object->item) {
      HouseItem::Account => 'Person',
      HouseItem::Key => 'key',
      HouseItem::Dump => 'export',
      default => $type === null ? 'activity' : SidebarEntry::glyphOf($type),
    };
  }

  public function href(HouseObject $object): string
  {
    return match ($object->item) {
      HouseItem::Account => '/#home-accounts',
      HouseItem::Key => '/#home-grants',
      HouseItem::Locale => '/#home-languages',
      HouseItem::Collection => '/c/' . $object->id,
      HouseItem::Dump => '/export',
    };
  }

  public function what(HouseObject $object, Activity $activity): string
  {
    return $this->words->get('change.what', ['type' => $this->words->get($this->about($object, $activity)),
      'done' => implode(', ', $this->done($object, $activity))]);
  }

  /* An arrangement is about the fields it names, the line's name saying whose. */
  private function about(HouseObject $object, Activity $activity): string
  {
    $named = $object->item === HouseItem::Collection && $activity->action === ActionType::Update
      ? count(array_unique(array_merge(...array_map(fn (string $what): array
        => $activity->result?->list($what) ?? [], array_keys(self::ARRANGED))))) : 0;

    return match (true) {
      $named === 1 => 'change.about.field',
      $named > 1 => 'change.about.fields',
      default => 'change.about.' . $object->item->value,
    };
  }

  /** @return list<string> */
  private function done(HouseObject $object, Activity $activity): array
  {
    $kept = $activity->result ?? new Props([]);

    return match (true) {
      $activity->action === ActionType::Delete => [$this->words->get('change.removed_item')],
      $object->item === HouseItem::Account => $this->account($activity->action, $kept),
      $object->item === HouseItem::Collection => $this->arranged($object, $kept),
      $object->item === HouseItem::Dump => $this->dumped($kept),
      $activity->action === ActionType::Create => [$this->words->get($object->item === HouseItem::Key
        ? 'change.issued' : 'change.added')],
      $kept->has('replaced') => [$this->words->get('change.replaced_key')],
      $kept->has('revoked') => [$this->words->get('change.revoked_key')],
      $kept->has('narrowed') => [$this->words->get('change.narrowed')],
      default => [$this->words->get('change.made_default')],
    };
  }

  /** @return list<string> */
  private function account(ActionType $action, Props $kept): array
  {
    $role = $kept->has('role') ? ['role' => $this->words->get('account.role.' . $kept->string('role'))] : [];
    if ($action === ActionType::Create) {
      return [$this->words->get('change.account_made', $role)];
    }
    $said = array_keys(array_filter([
      $kept->bool('disabled') === true ? 'change.disabled' : 'change.enabled' => $kept->has('disabled'),
      'change.account_role' => $kept->has('role'),
      'change.account_email' => $kept->has('email'),
      'change.account_scope' => $kept->has('scope'),
    ]));

    return $said === [] ? [$this->words->get('change.changed')]
      : array_map(fn (string $key): string => $this->words->get($key, $role), $said);
  }

  /* The fields by the names the reader's admin gives them.
     @return list<string> */
  private function arranged(HouseObject $object, Props $kept): array
  {
    $collection = $this->collection(null, $object);
    $type = $collection === null ? null : $this->profile->byType($collection->type);
    $said = [];
    foreach (self::ARRANGED as $what => $word) {
      $keys = array_filter($kept->list($what), is_string(...));
      if ($keys !== []) {
        $said[] = $this->words->get($word, ['fields' => implode(', ', array_map(
          fn (string $key): string => $type?->field($key)?->label ?? $key, $keys))]);
      }
    }

    return $said === [] ? [$this->words->get('change.arranged')] : $said;
  }

  /** @return list<string> */
  private function dumped(Props $kept): array
  {
    return [$this->words->get('change.dumped', ['records' => (string) $kept->int('records'),
      'files' => (string) $kept->int('files')])];
  }

  /* The read scope a dump ran under, by the types' names in the reader's language. */
  private function scope(Props $kept): string
  {
    $scope = $kept->string('scope');

    return $scope === null ? $this->words->get('change.dump_all') : implode(', ', array_map(
      fn (string $type): string => $this->words->get('type_one.' . $type), explode(' ', $scope)));
  }

  /* Null for a reader outside the type's read scope: what its records would not tell them either. */
  private function collection(?Account $account, HouseObject $object): ?Collection
  {
    try {
      $collection = $this->collections->bySlug(new Slug($object->id));
    } catch (MalformedValue) {
      return null;
    }

    return $collection === null || ($account !== null && !$account->read->allows(basename($collection->type)))
      ? null : $collection;
  }

  private function person(string $id): ?string
  {
    try {
      return (string) $this->entities->find(new EntityId($id))->props->string('name');
    } catch (EntityNotFound) {
      return null;
    }
  }
}
