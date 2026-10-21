<?php

declare(strict_types=1);

namespace Admin;

/* A work's group from its record screen's Languages card: a translation made
   as a draft, a record already written in another locale linked in, and a
   member let go; and the copy that leaves the group, here since a translation
   is a copy too. A house moving in brings its German and its English blog side
   by side, and a link is how they become one group without a copy made and the
   original deleted. The record named is joined to this one's root and never the
   other way, so two groups are never merged; `Storage\Collisions` refuses what
   the group cannot hold, in the save. The root is never let go, since its
   members name it. */

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Relation;
use Domain\Slug;
use Domain\Status;
use Domain\TranslationGroup;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\Predicate;
use Profile\Profile;
use Storage\Collection;
use Storage\EntityRepository;
use Storage\RelationRepository;
use Storage\Transaction;
use Support\Clock;
use Support\Uuid;

final readonly class TranslationController
{
  public function __construct(
    private CollectionController $collections,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private Authorizer $authorizer,
    private SpineEdits $spine,
    private Profile $profile,
    private Predicate $predicates,
    private Uuid $uuid,
    private Clock $clock,
    private Csrf $csrf,
    private \PDO $db,
    private Transaction $transaction = new Transaction(),
  ) {}

  /* A work's second language is a second record: a draft in that locale,
     a copy of this one's values and edges to translate from — where the type
     also labels, the words already kept for that locale — one member of its
     group, under the slug its label gives, as the address gives one. The type
     decides, so a labelled thing is refused by name, and a locale the group
     already holds opens the member written in it. */
  public function translate(Account $account, Request $request, string $slug, string $id): Response
  {
    [$collection, $entity] = $this->aimed($account, $request, $slug, $id);
    $locale = $this->spine->locale($entity, $request->form('locale'));
    $saved = $this->transaction->saved($this->db, fn (): Entity
      => $this->spine->member($entity, $locale) ?? $this->translation($entity, $locale));

    return Response::redirect(Rows::address($collection, $saved));
  }

  private function translation(Entity $entity, Locale $locale): Entity
  {
    $type = $this->profile->byType($entity->type);
    $now = $this->clock->now()->format(Clock::STORED);

    return $this->copied($entity, new Entity(new EntityId($this->uuid->generate()), $entity->type, $locale,
      Slug::from((new Label())->of($entity, $type), 'record'),
      (new LocalisedText($type))->forked($entity, $locale), $now, $now, Status::Draft,
      TranslationGroup::rootOf($entity)));
  }

  /* A record to change into one of its own, an event's next date, a product's
     second variant: a draft in its locale holding its values and edges but the
     ones held once, which it would collide on, under the next free slug its
     label gives, in no group and without a window. It opens for editing, since
     a copy is made to be changed. */
  public function duplicate(Account $account, Request $request, string $slug, string $id): Response
  {
    [$collection, $entity] = $this->aimed($account, $request, $slug, $id);
    $type = $this->profile->byType($entity->type);
    if (!$type->copies()) {
      throw MalformedValue::named('type whose records are copied', $type->name);
    }
    $now = $this->clock->now()->format(Clock::STORED);
    $draft = new Entity(new EntityId($this->uuid->generate()), $entity->type, $entity->locale,
      Slug::from((new Label())->of($entity, $type), 'record'),
      new Props(array_diff_key($entity->props->document(), array_flip($type->uniqueKeys()))), $now, $now);
    $saved = $this->transaction->saved($this->db, fn (): Entity => $this->copied($entity, $draft));

    return Response::redirect(Rows::address($collection, $saved) . '#edit');
  }

  /* The draft saved with the record's own edges, as they point now. */
  private function copied(Entity $entity, Entity $draft): Entity
  {
    $edges = array_map(fn (Relation $edge): Relation => new Relation($this->predicates, $draft->id,
      $edge->predicate, $edge->object, $edge->objectType, $edge->objectTerm, $edge->position, $edge->qualifiers),
      $this->relations->outbound($entity->id));

    return $this->entities->save($draft, $edges);
  }

  public function link(Account $account, Request $request, string $slug, string $id): Response
  {
    [$collection, $entity] = $this->aimed($account, $request, $slug, $id);
    $joining = $this->record($collection, $this->named($request));
    $this->spine->locale($entity, $joining->locale->tag);
    $this->regrouped($joining->withTranslationOf(TranslationGroup::rootOf($entity)));

    return Response::redirect(Rows::address($collection, $entity));
  }

  public function unlink(Account $account, Request $request, string $slug, string $id): Response
  {
    [$collection, $entity] = $this->aimed($account, $request, $slug, $id);
    $leaving = $this->record($collection, $this->named($request));
    if ($leaving->translationOf?->value !== TranslationGroup::rootOf($entity)->value) {
      throw MalformedValue::named('translation in this group', $leaving->id->value);
    }
    $this->regrouped($leaving->withTranslationOf(null));

    return Response::redirect(Rows::address($collection, $entity));
  }

  private function regrouped(Entity $member): void
  {
    $this->transaction->saved($this->db,
      fn (): Entity => $this->entities->save($member, $this->relations->outbound($member->id)));
  }

  private function named(Request $request): string
  {
    $named = $request->form('record');

    return is_string($named) ? $named : throw MalformedValue::notA('text', 'record');
  }

  /** @return array{Collection, Entity} */
  private function aimed(Account $account, Request $request, string $slug, string $id): array
  {
    $this->csrf->require($request);
    $collection = $this->collections->readable($account, $slug);
    $this->authorizer->requireHuman(Capability::WriteContent, $collection);

    return [$collection, $this->record($collection, $id)];
  }

  /* Another collection's record, a trashed one and a malformed id all read as none. */
  private function record(Collection $collection, string $id): Entity
  {
    try {
      $entity = $this->entities->find(new EntityId($id));
    } catch (MalformedValue) {
      throw EntityNotFound::withId($id);
    }
    if ($entity->type !== $collection->type || $entity->deletedAt !== null) {
      throw EntityNotFound::withId($id);
    }

    return $entity;
  }
}
