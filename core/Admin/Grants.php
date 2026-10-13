<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Grant;
use Auth\GrantCapability;
use Auth\GrantRepository;
use Auth\Token;
use Domain\Entity;
use Domain\EntityId;
use Domain\Status;
use Domain\Exceptions\EntityNotFound;
use Profile\Profile;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\CollectionField;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Support\Clock;
use Support\Words;

/* The grants a collection's badges draw, each reviewed, and every rule worded as
   the sentence it was written as. Only live tokens mark: an expired or revoked
   one draws nothing, or the badge would keep reporting an integration that has
   already stopped. A rule for a field the collection no longer carries has no
   column to stand on, so it is drawn on the collection's badge, marked stale.
   A rule's subject is named as a chip names its target: by its label where the
   reader reads it, else as missing, since the badge is drawn for every colleague
   who opens the collection and a name is the disclosure a record was not. A
   record's own badge reviews the rules bound to it alone and offers `set` and
   `read` on it, never `create`, which no record can bind. */
final readonly class Grants
{
  /* The marker the issue form writes the typed name over. */
  public const NAME = '{name}';
  private const WEEK = 'P7D';

  public function __construct(
    private GrantRepository $grants,
    private LiveKeys $keys,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private ActivityLog $log,
    private Rows $rows,
    private Profile $profile,
    private KeyFacts $facts,
    private Clock $clock,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  /** @return array<string, list<GrantReview>> by field key, '' for the collection's own */
  public function reviews(Account $account, Collection $collection): array
  {
    $live = $this->keys->live();
    $carried = array_map(fn (CollectionField $field): string => $field->key, $collection->fields);
    $reviews = [];
    foreach ($this->grants->forCollection($collection->slug) as $rule) {
      $token = $live[$rule->tokenId] ?? null;
      if ($token !== null) {
        $anchor = in_array($rule->field, $carried, true) ? (string) $rule->field : '';
        $reviews[$anchor][] = $this->review($account, $token, $rule, $live);
      }
    }

    return $reviews;
  }

  /* The badge on one record's screen, for whoever issues: the rules bound to it. */
  public function record(Account $account, Collection $collection, Entity $record): Badge
  {
    $live = $this->keys->live();
    $reviews = [];
    foreach ($this->grants->forCollection($collection->slug) as $rule) {
      $token = $live[$rule->tokenId] ?? null;
      if ($token !== null && $rule->subject?->value === $record->id->value) {
        $reviews[] = $this->review($account, $token, $rule, $live);
      }
    }

    return new Badge(null, $this->label->of($record, $this->profile->byType($record->type)), $reviews,
      $this->offers($account, $collection, null, $record->id), $record->id->value);
  }

  /**
   * Every anchor that draws a badge: those with grants for everyone, and for an
   * account that may issue, every anchor.
   *
   * @param list<Column> $columns
   * @return array<string, Badge> by field key, '' for the collection
   */
  public function badges(Account $account, Collection $collection, array $columns, bool $issues): array
  {
    $reviews = $this->reviews($account, $collection);
    $anchors = ['' => $collection->name];
    foreach ($columns as $column) {
      $anchors[$column->field->key] = $column->label;
    }
    $badges = [];
    foreach ($anchors as $key => $title) {
      $field = $key === '' ? null : (string) $key;
      if ($issues || isset($reviews[$key])) {
        $badges[$key] = new Badge($field, $title, $reviews[$key] ?? [],
          $issues ? $this->offers($account, $collection, $field) : []);
      }
    }

    return $badges;
  }

  /** @return list<GrantOffer> */
  private function offers(Account $account, Collection $collection, ?string $field,
    ?EntityId $subject = null): array
  {
    $public = in_array(Status::Published, Status::heldBy($this->profile->byType($collection->type)), true);
    /* A collection offers set whole, since a channel that creates its records
       corrects them too, and arranging, which is the collection's;
       a field or a record offers set on itself alone. */
    $offered = $field === null && $subject === null ? [GrantCapability::Create, GrantCapability::Set,
      GrantCapability::Read, GrantCapability::Arrange] : [GrantCapability::Set, GrantCapability::Read];

    $offers = [];
    foreach ($offered as $capability) {
      $read = $capability === GrantCapability::Read;
      $note = match (true) {
        $read && !$public && $subject === null => $this->words->get('grant.ungated',
          ['collection' => $collection->name]),
        $capability === GrantCapability::Create => $this->words->get('grant.graph'),
        $capability === GrantCapability::Arrange => $this->words->get('grant.arrange_note'),
        default => null,
      };
      $offers[] = new GrantOffer($capability, $this->reaches($capability, $collection, $field),
        $note, $read && $public ? $this->words->get('grant.unpublished_offer') : null);
    }

    return $offers;
  }

  /* The rule as the sentence it was written as; `$name` stands where the token's
     name goes, so the issue form can write it as it is typed. */
  public function sentence(Grant $rule, string $name, Account $account): string
  {
    $said = $this->words->get('grant.sentence', ['name' => $name, 'does' => $this->does($rule)]);

    return implode(' · ', [$said, ...$this->qualifiers($rule, $account)]);
  }

  /* An offer stands after its verb, so it names only what the verb reaches; a record's reaches its collection. */
  private function reaches(GrantCapability $capability, Collection $collection, ?string $field): string
  {
    return $this->words->get('grant.offer_' . $capability->value . ($field === null ? '' : '_field'),
      ['collection' => $collection->name, 'field' => $field === null ? '' : $this->fieldLabel($collection, $field)]);
  }

  /* What the rule allows, the sentence's second half. */
  private function does(Grant $rule): string
  {
    $collection = $this->collections->bySlug($rule->collection);
    $values = ['collection' => $collection?->name ?? $rule->collection->value,
      'field' => $rule->field === null ? '' : $this->fieldLabel($collection, $rule->field)];

    return $this->words->get('grant.may_' . $rule->capability->value . ($rule->field === null ? '' : '_field'),
      $values);
  }

  /** @param array<string, Token> $live */
  private function review(Account $account, Token $token, Grant $rule, array $live): GrantReview
  {
    $others = array_values(array_filter($this->grants->forToken($token),
      fn (Grant $other): bool => $other->id !== $rule->id));
    $newer = array_find($live, fn (Token $other): bool => $this->keys->alike($other->label, $token->label)
      && strcmp($other->id, $token->id) > 0);
    $since = $this->clock->now()->sub(new \DateInterval(self::WEEK))->format(Clock::STORED);

    return new GrantReview($token, $rule, $this->sentence($rule, $token->label, $account),
      $this->facts->issued($token), $this->facts->expiry($token), $this->facts->used($token),
      $this->words->get('grant.writes', ['n' => (string) $this->log->writesBy($token->id, $since)]),
      $this->lines($others, $account),
      $newer === null ? null : $this->facts->replaced($token, $newer), $this->stale($rule));
  }

  /* A key's rules, one line per set of collections allowed the same, since a
     setup key holds four rules on every collection and a sentence each hides
     which collections it reaches, as a line each hides that they are all.
     @param list<Grant> $others
     @return list<string> */
  public function lines(array $others, Account $account): array
  {
    $lines = [];
    foreach ($others as $rule) {
      $collection = $this->collections->bySlug($rule->collection);
      $key = 'grant.does_' . $rule->capability->value . ($rule->field === null ? '' : '_field');
      $field = $rule->field === null ? '' : $this->fieldLabel($collection, $rule->field);
      $lines[$collection?->name ?? $rule->collection->value][] = implode(', ',
        [$this->words->get($key, ['field' => $field]), ...$this->qualifiers($rule, $account)]);
    }
    $alike = [];
    foreach ($lines as $name => $parts) {
      $alike[implode(' · ', $parts)][] = (string) $name;
    }

    return array_map(fn (string $does, array $names): string => $this->reach($names) . ': ' . $does,
      array_keys($alike), $alike);
  }

  /** @param list<string> $names */
  private function reach(array $names): string
  {
    $every = array_map(fn (Collection $collection): string => $collection->name, $this->collections->all());

    return array_diff($every, $names) === [] ? $this->words->get('grant.every_collection')
      : implode(', ', $names);
  }

  /** @return list<string> what narrows a rule: reading past the published, or one subject */
  private function qualifiers(Grant $rule, Account $account): array
  {
    return array_values(array_filter([$rule->unpublished ? $this->words->get('grant.unpublished') : '',
      $rule->subject === null ? '' : $this->words->get('grant.subject',
        ['subject' => $this->subject($account, $rule->subject)])]));
  }

  public function stale(Grant $rule): ?string
  {
    $collection = $this->collections->bySlug($rule->collection);
    $carried = $collection !== null && ($rule->field === null
      || array_any($collection->fields, fn (CollectionField $field): bool => $field->key === $rule->field));

    return $carried ? null
      : $this->words->get('grant.stale', ['what' => $rule->field ?? $rule->collection->value]);
  }

  private function fieldLabel(?Collection $collection, string $key): string
  {
    $defined = $collection === null ? null : $this->profile->byType($collection->type)->field($key);

    return $defined?->label ?? $key;
  }

  private function subject(Account $account, EntityId $id): string
  {
    try {
      $record = $this->entities->find($id);
    } catch (EntityNotFound) {
      $record = null;
    }
    if ($record === null || $record->deletedAt !== null || !$this->rows->readable($account, $record)) {
      return $this->words->get('cell.gone');
    }

    return $this->label->of($record, $this->profile->byType($record->type));
  }
}
