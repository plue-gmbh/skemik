<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRefused;
use Auth\AccountRepository;
use Auth\Authorizer;
use Auth\Capability;
use Auth\LoginCode;
use Auth\Role;
use Auth\Session;
use Auth\TypeScope;
use Domain\ActionType;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\ValueTaken;
use Domain\Props;
use Domain\Slug;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\Profile;
use Profile\TypeDefinition;
use Profile\Validator;
use Storage\ActivityLog;
use Storage\EntityRepository;
use Storage\HouseItem;
use Storage\HouseObject;
use Storage\SettingStore;
use Storage\Transaction;
use Support\Clock;
use Support\Json;
use Support\Uuid;
use Support\Words;

/* Accounts, `manageAccounts` only, written from the Accounts card's dialogs
   (`view/partials/account-dialog.php`). A new account holds a `Person` picked
   through the search every edge uses, or one made beside it in the same
   transaction, so a refused address leaves no stray person. No credential is
   set, since there is none: the person logs in when they ask for a code. The
   role decides which scopes exist: an owner reads and writes everything and a
   viewer writes nothing, so a viewer's write scope is its read scope and
   `write ⊆ read` holds without a second list. What the dialog can send wrong is
   answered in the reader's words; the last owner stays guarded in
   `Auth\AccountRepository`, inside the write's lock. */
final readonly class AccountController
{
  public function __construct(
    private Authorizer $authorizer,
    private AccountRepository $accounts,
    private EntityRepository $entities,
    private Validator $validator,
    private Profile $profile,
    private SettingStore $settings,
    private Uuid $uuid,
    private Clock $clock,
    private Csrf $csrf,
    private Words $words,
    private \PDO $db,
    private ActivityLog $log,
    private Session $session,
    private LoginCode $codes,
    private Transaction $transaction = new Transaction(),
    private Json $json = new Json(),
  ) {}

  public function create(Request $request): Response
  {
    $this->guard($request);
    $sent = $request->form;
    $picked = $this->picked($sent['person'] ?? '');
    $unfit = $this->unfit($sent) ?? $this->unheld($picked, trim($sent['name'] ?? ''));
    if ($unfit !== null) {
      return $this->refused($unfit);
    }
    [$role, $write, $read] = $this->standing($sent);

    return $this->answered(function () use ($sent, $picked, $role, $write, $read): array {
      $made = [];
      $this->transaction->run($this->db, function () use ($sent, $picked, $role, $write, $read, &$made): void {
        $person = $picked ?? $this->person(trim($sent['name'] ?? ''));
        $made = ['id' => $this->accounts->create($person, $sent['email'] ?? '', $role, $write, $read)->id];
        $this->logged(ActionType::Create, $made['id'], $person->id, ['role' => $role->value]);
      });

      return $made;
    }, 201);
  }

  /* Disabling keeps the day it first happened, so saving a disabled account's
     role again does not move it. */
  public function edit(Request $request, string $id): Response
  {
    $this->guard($request);
    $held = $this->accounts->find($id);
    parse_str($request->body, $parsed);
    $sent = array_filter($parsed, is_string(...));
    $unfit = $this->unfit($sent);
    if ($unfit !== null) {
      return $this->refused($unfit);
    }
    [$role, $write, $read] = $this->standing($sent);
    $disabled = ($sent['disabled'] ?? '') === '1' ? $held->disabledAt ?? $this->now() : null;

    return $this->answered(function () use ($held, $sent, $role, $write, $read, $disabled): array {
      $saved = new Account($held->id, $held->person, $sent['email'] ?? '', $role,
        $write, $read, $held->createdAt, $held->lastLoginAt, $disabled);
      $this->accounts->save($saved);
      $this->ended($held, $saved);
      $changed = $this->changed($held, $saved);
      if ($changed !== []) {
        $this->logged(ActionType::Update, $held->id, $held->person, $changed);
      }

      return ['id' => $held->id];
    });
  }

  /* The account goes and its person stays a record, since who wrote what is
     still theirs. */
  public function remove(Request $request, string $id): Response
  {
    $this->guard($request);
    $held = $this->accounts->find($id);

    return $this->answered(function () use ($held): array {
      $this->accounts->delete($held);
      $this->logged(ActionType::Delete, $held->id, $held->person);

      return ['id' => $held->id];
    });
  }

  /* A login was proven for the address the account had, and a disabled one's
     would be back the day it is enabled, so both end with the codes on their way.
     Whoever changes their own address keeps this browser, where a typo is still seen. */
  private function ended(Account $held, Account $saved): void
  {
    if ($saved->disabledAt !== null || $held->email !== $saved->email) {
      $this->codes->forget($held);
      $saved->disabledAt === null ? $this->session->endOthers($held->id) : $this->session->endAll($held->id);
    }
  }

  /* Which parts an edit changed, and the role it now holds, never the address,
     which is personal and which the log keeps nothing of.
     @return array<string, string|bool> */
  private function changed(Account $held, Account $saved): array
  {
    return array_filter([
      'role' => $held->role === $saved->role ? null : $saved->role->value,
      'email' => $held->email === $saved->email ? null : true,
      'scope' => $held->write == $saved->write && $held->read == $saved->read ? null : true,
      'disabled' => ($held->disabledAt === null) === ($saved->disabledAt === null) ? null
        : $saved->disabledAt !== null,
    ], fn (string|bool|null $part): bool => $part !== null);
  }

  /** @param array<string, string|bool> $said */
  private function logged(ActionType $type, string $account, EntityId $person, array $said = []): void
  {
    $this->log->record($type, new HouseObject(HouseItem::Account, $account),
      new Props(['person' => $person->value, ...$said]));
  }

  private function guard(Request $request): void
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::ManageAccounts);
  }

  /** @param array<array-key, string> $sent */
  private function unfit(array $sent): ?string
  {
    $role = Role::tryFrom($sent['role'] ?? '');
    $used = match ($role) {
      Role::Editor => ['write', 'read'],
      Role::Viewer => ['read'],
      default => [],
    };

    return match (true) {
      filter_var(Account::address($sent['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false
        => $this->words->get('account.address'),
      array_any($used, fn (string $scope): bool => trim($sent[$scope] ?? '-') === '')
        => $this->words->get('account.scope_empty'),
      default => $role === null ? null : $this->outside($sent),
    };
  }

  /** @param array<array-key, string> $sent */
  private function outside(array $sent): ?string
  {
    [, $write, $read] = $this->standing($sent);
    if ($read->covers($write)) {
      return null;
    }
    $every = array_map(fn (TypeDefinition $type): string => $type->name, $this->profile->types());
    $words = array_map(fn (string $type): string => $this->words->get('type.' . $type),
      array_diff($write->types ?? $every, $read->types ?? $every));

    return $this->words->get('account.write_outside', ['types' => implode(', ', $words)]);
  }

  /** @param array<array-key, string> $sent
      @return array{Role, TypeScope, TypeScope} the role, what it writes and what it reads as staff */
  private function standing(array $sent): array
  {
    $role = Role::tryFrom($sent['role'] ?? '') ?? throw MalformedValue::named('role', $sent['role'] ?? '');
    $read = $role === Role::Owner ? TypeScope::all() : $this->scope($sent['read'] ?? null);
    $write = match ($role) {
      Role::Owner => TypeScope::all(),
      Role::Editor => $this->scope($sent['write'] ?? null),
      Role::Viewer => $read,
    };

    return [$role, $write, $read];
  }

  /* The types ticked, or every type where none were narrowed; a name that is
     not one of the thirty is a request made past the dialog. */
  private function scope(?string $ticked): TypeScope
  {
    if ($ticked === null) {
      return TypeScope::all();
    }
    $types = preg_split('/\s+/', trim($ticked)) ?: [];

    return array_all($types, $this->profile->has(...)) ? TypeScope::only($types)
      : throw MalformedValue::named('type in a scope', $ticked);
  }

  /* The search offers no trashed person. */
  private function picked(string $id): ?Entity
  {
    if ($id === '') {
      return null;
    }
    $person = $this->entities->find(new EntityId($id));

    return $person->deletedAt === null ? $person : throw EntityNotFound::withId($id);
  }

  private function unheld(?Entity $picked, string $name): ?string
  {
    $holder = $picked === null ? null : $this->accounts->heldBy($picked->id);
    if ($picked !== null && $holder !== null) {
      return $this->words->get('account.held', ['name' => (string) $picked->props->string('name'),
        'email' => $holder->email]);
    }

    return $picked === null && $name === '' ? $this->words->get('account.who') : null;
  }

  /* A draft, as the owner `/install` makes is: typing a colleague's name
     publishes nothing. */
  private function person(string $name): Entity
  {
    $type = $this->profile->byType('Person');
    $props = new Props(['name' => $name]);
    $violations = $this->validator->check($type, $props->document(), []);
    if ($violations !== []) {
      throw MalformedValue::named($violations[0]->field, $violations[0]->reason);
    }
    $now = $this->now();

    return $this->entities->save(new Entity(new EntityId($this->uuid->generate()), $type->url(),
      $this->settings->locales()[0], Slug::from($name, 'person'), $props, $now, $now), []);
  }

  /* The repository's two refusals, the last owner and an address another
     account logs in with, in the reader's words.
     @param \Closure(): array<string, string> $work */
  private function answered(\Closure $work, int $status = 200): Response
  {
    try {
      return Response::json($this->json->encode($work()), $status);
    } catch (AccountRefused) {
      return $this->refused($this->words->get('account.last_owner'));
    } catch (ValueTaken) {
      return $this->refused($this->words->get('account.taken'));
    }
  }

  private function refused(string $sentence): Response
  {
    return Response::json($this->json->encode(['error' => $sentence]), 422);
  }

  private function now(): string
  {
    return $this->clock->now()->format(Clock::STORED);
  }
}
