<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Grant;
use Auth\GrantCapability;
use Auth\GrantRepository;
use Auth\Token;
use Auth\TokenRepository;
use Domain\ActionType;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Props;
use Domain\Slug;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\CollectionField;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\HouseItem;
use Storage\HouseObject;
use Support\Clock;
use Support\Json;
use Support\Uuid;

/* Tokens and grants, `manageTokens` only, through one panel from three anchors,
   a collection's badge, a field's popover and a record's badge, because a
   credential with two implementations is two answers to what this string may do,
   and *let this one thing be written from outside* is thought while looking at it.
   No route adds a rule to an issued token: a live grant is narrowed and never
   widened, so a second thing under a name is a re-issue, a new secret holding the
   old rules, the old one left to run out so the integration moves without
   downtime or, ticked, every older secret of the name stopped at once, since a
   dozen live secrets of one name came of the first way. A live name typed is
   answered with what adding to it means before anything is issued, or with why
   it cannot be, where the new key would read a collection two ways. */
final readonly class TokenController
{
  public function __construct(
    private Authorizer $authorizer,
    private GrantRepository $grants,
    private TokenRepository $tokens,
    private CollectionStore $collections,
    private EntityRepository $entities,
    private LiveKeys $keys,
    private Uuid $uuid,
    private Clock $clock,
    private Csrf $csrf,
    private ActivityLog $log,
    private Json $json = new Json(),
  ) {}

  /* The secret is in the answer and nowhere after it: the page shows it once. A
     live name, in any case, is answered with what adding to it means until the
     form says it adds, and the new key keeps the live one's spelling. */
  public function issue(Account $account, Request $request): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::ManageTokens);
    if ((string) $request->form('from') !== '') {
      return $this->reissue($account, $request, $this->tokens->find((string) $request->form('from')));
    }
    $previous = $this->keys->named((string) $request->form('label'));
    $token = $this->token($account, $previous?->label ?? trim((string) $request->form('label')), $request);
    $rules = $this->carried($previous, $token, $this->rules($request));
    $refused = $this->refused($token, $rules);
    if ($refused === null && $previous !== null && $request->form('adds') !== '1') {
      return Response::json($this->json->encode(['error' => $this->keys->adding($previous)]), 409);
    }

    return $refused ?? $this->issued($request, $token, $rules);
  }

  /* Said before adding is: a name whose new key would read a collection two
     ways is refused whatever else it holds, and the form is answered so.
     @param list<Grant> $rules */
  private function refused(Token $token, array $rules): ?Response
  {
    $twoWays = Grant::readTwoWays($rules);

    return $twoWays === null ? null : Response::json($this->json->encode(['error' => $this->keys->twoWays(
      $token->label, $this->collection($twoWays->value)->name)]), 422);
  }

  /* The register's re-issue: the ticked rules of the name's newest key on a new
     one, so it narrows and never widens, since nothing but that key's own rules
     can be ticked. */
  private function reissue(Account $account, Request $request, Token $from): Response
  {
    if ($this->keys->named($from->label)?->id !== $from->id) {
      throw MalformedValue::named('newest live key of ' . $from->label, $from->id);
    }
    $ticked = explode(',', (string) $request->form('rules'));
    $rules = array_values(array_filter($this->grants->forToken($from),
      fn (Grant $each): bool => in_array($each->id, $ticked, true)));
    if ($rules === []) {
      throw MalformedValue::named('rule of ' . $from->label, (string) $request->form('rules'));
    }
    $token = $this->token($account, $from->label, $request);
    $rules = $this->carried(null, $token, $rules);

    return $this->refused($token, $rules) ?? $this->issued($request, $token, $rules);
  }

  /* The older secrets of the name are read before the new one is written, so
     the stop ticked never stops the key just issued. Each is logged as replaced,
     not revoked: read under the new key's line, a revocation reads as its own.
     @param list<Grant> $rules */
  private function issued(Request $request, Token $token, array $rules): Response
  {
    $older = $request->form('replace') === '1' ? $this->keys->liveNamed($token->label) : [];
    $secret = $this->grants->issue($token, $rules);
    $this->logged(ActionType::Create, $token);
    foreach ($older as $each) {
      $this->tokens->revoke($each);
      $this->logged(ActionType::Update, $each, 'replaced');
    }

    return Response::json($this->json->encode(['secret' => $secret, 'label' => $token->label]), 201);
  }

  private function token(Account $account, string $label, Request $request): Token
  {
    return (new Token($this->uuid->generate(), $label, $this->clock->now()->format(Clock::STORED),
      $account->person->value, $this->expiry((string) $request->form('until'))))->issuable();
  }

  /* The live key's rules and the new ones, on the new key.
     @param list<Grant> $rules
     @return list<Grant> */
  private function carried(?Token $previous, Token $token, array $rules): array
  {
    $kept = $previous === null ? [] : array_filter($this->grants->forToken($previous),
      fn (Grant $each): bool => !array_any($rules, fn (Grant $rule): bool => $this->same($each, $rule)));

    return array_map(fn (Grant $each): Grant => new Grant($this->uuid->generate(), $token->id, $each->collection,
      $each->capability, $token->createdAt, $each->field, $each->subject, $this->unpublished($each, $rules)),
      [...array_values($kept), ...$rules]);
  }

  /* Takes power away, which is safe at any moment; the token's last rule takes
     the token with it. */
  public function remove(Request $request, string $token, string $rule): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::ManageTokens);
    $held = array_find($this->grants->forToken($this->tokens->find($token)),
      fn (Grant $each): bool => $each->id === $rule) ?? throw EntityNotFound::withId($rule);
    $this->grants->remove($held);
    $after = $this->tokens->find($token);
    $this->logged(ActionType::Update, $after, $after->revokedAt === null ? 'narrowed' : 'revoked');

    return Response::redirect($this->back($request));
  }

  /* The screen the rule was removed from, a collection's or a record's, else home:
     a form's `back` is sent as it stands, so only a path of that shape is followed. */
  /** @psalm-taint-escape header a path under /c/ proven by its pattern, with no room for a line break */
  private function back(Request $request): string
  {
    $back = (string) $request->form('back');

    return preg_match('#^/c/[a-z0-9-]+(/[a-z0-9-]+)?(\?[^\s]*)?$#D', $back) === 1 ? $back : '/';
  }

  /* The whole key at once, from the register; its caller is told what an
     expired key is told. */
  public function revoke(Request $request, string $token): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::ManageTokens);
    $held = $this->tokens->find($token);
    $this->tokens->revoke($held);
    if ($held->revokedAt === null) {
      $this->logged(ActionType::Update, $held, 'revoked');
    }

    return Response::redirect('/');
  }

  private function logged(ActionType $type, Token $token, ?string $done = null): void
  {
    $this->log->record($type, new HouseObject(HouseItem::Key, $token->id),
      $done === null ? null : new Props([$done => true]));
  }

  /* The ticked capabilities and the collections, each sent as one comma list:
     create and read on one anchor are two rules of one key, and the set-up key
     of the register is one rule per collection. A field names one collection.
     @return list<Grant> */
  private function rules(Request $request): array
  {
    $slugs = array_values(array_unique(array_filter(explode(',', (string) $request->form('collection')))));
    $field = (string) $request->form('field');
    if ($slugs === [] || ($field !== '' && count($slugs) > 1)) {
      throw MalformedValue::named('collection', (string) $request->form('collection'));
    }
    $sent = array_values(array_unique(array_filter(explode(',', (string) $request->form('capability')))));
    if ($sent === []) {
      throw MalformedValue::named('capability', '');
    }
    $unpublished = $request->form('unpublished') === '1';
    $subject = (string) $request->form('subject');
    if ($subject !== '' && count($slugs) > 1) {
      throw MalformedValue::named('collection of one record', (string) $request->form('collection'));
    }
    $rules = [];
    foreach ($slugs as $slug) {
      $collection = $this->collection($slug);
      $bound = $subject === '' ? null : $this->subject($collection, $subject);
      $rules = [...$rules, ...$this->rulesOn($collection, $field, $sent, $unpublished, $bound)];
    }

    return $rules;
  }

  /** @param list<string> $sent
      @return list<Grant> */
  private function rulesOn(Collection $collection, string $field, array $sent, bool $unpublished,
    ?EntityId $subject): array
  {
    $carried = array_any($collection->fields, fn (CollectionField $each): bool => $each->key === $field);
    if ($field !== '' && !$carried) {
      throw MalformedValue::named('field of ' . $collection->name, $field);
    }

    return array_map(function (string $each) use ($collection, $field, $unpublished, $subject): Grant {
      $capability = GrantCapability::tryFrom($each) ?? throw MalformedValue::named('capability', $each);

      return new Grant('', '', $collection->slug, $capability, '', $field === '' ? null : $field, $subject,
        $unpublished && $capability === GrantCapability::Read);
    }, $sent);
  }

  /* The record a rule is bound to stands in the collection and not in the trash,
     or the rule would be stale the moment it is issued. */
  private function subject(Collection $collection, string $id): EntityId
  {
    try {
      $record = $this->entities->find(new EntityId($id));
    } catch (MalformedValue | EntityNotFound) {
      $record = null;
    }
    if ($record === null || $record->type !== $collection->type || $record->deletedAt !== null) {
      throw EntityNotFound::withId($id);
    }

    return $record->id;
  }

  /* A key reads a collection one way: a read rule issued for it moves the key's
     other read rules there with it, or two sentences on one badge would disagree
     about one reading, and the filter would take the narrower.
     @param list<Grant> $issued */
  private function unpublished(Grant $each, array $issued): bool
  {
    $read = array_find($issued, fn (Grant $rule): bool => $rule->capability === GrantCapability::Read
      && $rule->collection->value === $each->collection->value);

    return $each->capability === GrantCapability::Read && $read !== null ? $read->unpublished : $each->unpublished;
  }

  private function collection(string $slug): Collection
  {
    try {
      $collection = $this->collections->bySlug(new Slug($slug));
    } catch (MalformedValue) {
      $collection = null;
    }

    return $collection ?? throw EntityNotFound::withId($slug);
  }

  /* The last moment of the chosen day in the house's time, so a date means what
     it says; `Auth\Token` refuses one that is not in the future. */
  private function expiry(string $date): string
  {
    return $this->clock->parse($date . 'T23:59:59');
  }

  private function same(Grant $one, Grant $other): bool
  {
    return $one->collection->value === $other->collection->value && $one->field === $other->field
      && $one->capability === $other->capability && $one->subject?->value === $other->subject?->value;
  }
}
