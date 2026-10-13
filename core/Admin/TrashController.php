<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\Erasure;
use Profile\Profile;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RelationRepository;
use Storage\Trash;
use Support\Clock;
use Support\Template;
use Support\Words;

/* The trash: a delete is an undo and not a removal, immediate rather than
   pending, and the way back out of it. Every write takes the record's id, never
   its slug — a trashed record keeps its slug, so nothing can take its address
   meanwhile. The purge is the other verb, `eraseData` and not `writeContent`, and
   it is offered on the trash screen alone: a record is purged from the trash, so
   the record screen holds one verb and the wrong one cannot be clicked there.
   Whoever writes a collection sees its trash, since they filled it. Nothing
   empties it by itself: there is no daemon to, and a purge is a decision, so a
   deleted customer is held, whole and restorable, until the owner purges them.
   The table's ticked rows go in and come back out together, and the trash's
   ticked rows come back out or are purged together (`Storage\Trash`): every id
   is a record of the collection, in or out of its trash as the verb needs, before
   the first moves, the screen returned to offers the lot back as it offers one,
   and one dialog counts what a purge takes over the lot. */
final readonly class TrashController
{
  public function __construct(
    private CollectionController $collections,
    private EntityRepository $entities,
    private Trash $trash,
    private RelationRepository $relations,
    private ActivityLog $log,
    private Authorizer $authorizer,
    private Session $session,
    private Profile $profile,
    private Intake $intake,
    private Clock $clock,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
  ) {}

  /* Last trashed first: trashing stamps `updated_at`, and nothing edits a record there. */
  public function show(Account $account, Entity $person, Request $request, string $slug): Response
  {
    $collection = $this->collections->readable($account, $slug);
    $this->authorizer->requireHuman(Capability::WriteContent, $collection);
    if ($this->authorizer->readsAsPublic($account, $collection->type)) {
      throw EntityNotFound::withId($slug);
    }
    $query = new Query($collection->type, sort: 'updated_at', descending: true,
      page: max(1, (int) $request->query('page')))->inTrash();
    $title = $this->words->get('trash.title', ['collection' => $collection->name]);
    $main = $this->template->render('pages/trash', ['words' => $this->words, 'collection' => $collection,
      'title' => $title, 'rows' => array_map($this->row(...), $this->entities->list($query)),
      'total' => $this->entities->count($query), 'page' => $query->page,
      'purges' => $account->can(Capability::EraseData), 'purged' => $this->session->take('purged'),
      'csrf' => $this->csrf->token()]);

    return $this->collections->frame($account, $person, $collection, $title, $main, false,
      $this->collections->feed($request, $collection));
  }

  /* To the trash, at once and not pending: the collection screen it
     returns to offers it back. */
  public function delete(Account $account, Request $request, string $slug, string $id): Response
  {
    [$collection, $entity] = $this->aimed($account, $request, $slug, $id);
    $this->trash->delete($entity->id);
    $this->session->keep('trashed', $entity->id->value);

    return $this->backTo($collection, $request);
  }

  public function deleteMany(Account $account, Request $request, string $slug): Response
  {
    $collection = $this->writable($account, $request, $slug);
    $held = array_map(fn (string $id): Entity => $this->held($collection, $id), $this->ids($request));
    $this->trash->delete(...array_map(fn (Entity $entity): EntityId => $entity->id, $held));
    $this->session->keep('trashed', implode(',', array_map(fn (Entity $entity): string
      => $entity->id->value, $held)));

    return $this->backTo($collection, $request);
  }

  public function restore(Account $account, Request $request, string $slug, string $id): Response
  {
    $collection = $this->writable($account, $request, $slug);
    $entity = $this->trashed($collection, $id);
    $this->trash->restore($entity->id);

    return Response::redirect(Rows::address($collection, $entity));
  }

  public function restoreMany(Account $account, Request $request, string $slug): Response
  {
    $collection = $this->writable($account, $request, $slug);
    $trashed = array_map(fn (string $id): Entity => $this->trashed($collection, $id), $this->ids($request));
    $this->trash->restore(...array_map(fn (Entity $entity): EntityId => $entity->id, $trashed));

    return $this->backTo($collection, $request);
  }

  /* Comma-joined, since a form value that is a list is dropped at the door (`Http\Request`).
     @return list<string> */
  private function ids(Request $request): array
  {
    $ids = array_values(array_filter(explode(',', (string) $request->form('ids'))));

    return $ids === [] ? throw MalformedValue::notA('a list of ids', 'ids') : $ids;
  }

  /* For good, with the edges at it, unless a required edge names it
     (`Storage\Trash`). The name a person left in the log survives on purpose,
     so the dialog offers its redaction and nothing assumes it. */
  public function purge(Account $account, Request $request, string $slug, string $id): Response
  {
    $collection = $this->erasable($account, $request, $slug);

    return $this->purged($collection, [$this->trashed($collection, $id)], $request);
  }

  public function purgeMany(Account $account, Request $request, string $slug): Response
  {
    $collection = $this->erasable($account, $request, $slug);
    $lot = array_map(fn (string $id): Entity => $this->trashed($collection, $id), $this->ids($request));

    return $this->purged($collection, $lot, $request);
  }

  /** @param list<Entity> $lot */
  private function purged(Collection $collection, array $lot, Request $request): Response
  {
    $this->trash->purge(...array_map(fn (Entity $entity): EntityId => $entity->id, $lot));
    foreach ($lot as $entity) {
      $this->intake->release($entity);
      if ($entity->typeName() === 'Person' && $request->form('redact') !== null) {
        $this->log->redactActorName($entity->id);
      }
    }
    $label = fn (Entity $entity): string => (new Label())->of($entity, $this->profile->byType($entity->type));
    $this->session->keep('purged', count($lot) === 1 ? $this->words->get('purge.done', ['label' => $label($lot[0])])
      : $this->words->get('purge.done_many', ['n' => (string) count($lot)]));

    return Response::redirect('/trash/' . $collection->slug->value);
  }

  private function erasable(Account $account, Request $request, string $slug): Collection
  {
    $this->csrf->require($request);
    $collection = $this->collections->readable($account, $slug);
    $this->authorizer->requireHuman(Capability::EraseData);

    return $collection;
  }

  private function row(Entity $entity): TrashedRow
  {
    $type = $this->profile->byType($entity->type);

    return new TrashedRow($entity, (new Label())->of($entity, $type),
      $this->when((string) $entity->deletedAt), count($this->relations->inbound($entity->id)),
      $entity->typeName() === 'Person' && $this->log->namesActor($entity->id),
      $type->erasure === Erasure::Hold);
  }

  /* The house's wall time read back as a wall time, as `Cells` shows a moment. */
  private function when(string $stored): string
  {
    $local = new \DateTimeImmutable($this->clock->render($stored), new \DateTimeZone('UTC'));

    return (string) (new \IntlDateFormatter($this->words->language, \IntlDateFormatter::MEDIUM,
      \IntlDateFormatter::SHORT, 'UTC'))->format($local);
  }

  /** @return array{Collection, Entity} */
  private function aimed(Account $account, Request $request, string $slug, string $id): array
  {
    $collection = $this->writable($account, $request, $slug);

    return [$collection, $this->held($collection, $id)];
  }

  /* A record of the collection outside its trash; any other id reads as none. */
  private function held(Collection $collection, string $id): Entity
  {
    $entity = $this->find($id);

    return $entity->type !== $collection->type || $entity->deletedAt !== null
      ? throw EntityNotFound::withId($id) : $entity;
  }

  private function trashed(Collection $collection, string $id): Entity
  {
    $entity = $this->find($id);

    return $entity->type !== $collection->type || $entity->deletedAt === null
      ? throw EntityNotFound::withId($id) : $entity;
  }

  private function writable(Account $account, Request $request, string $slug): Collection
  {
    $this->csrf->require($request);
    $collection = $this->collections->readable($account, $slug);
    $this->authorizer->requireHuman(Capability::WriteContent, $collection);

    return $collection;
  }

  private function find(string $id): Entity
  {
    try {
      return $this->entities->find(new EntityId($id));
    } catch (MalformedValue) {
      throw EntityNotFound::withId($id);
    }
  }

  /* Back to the collection under the query the form carried, read as parameters only. */
  private function backTo(Collection $collection, Request $request): Response
  {
    parse_str((string) $request->form('back'), $parameters);
    $back = http_build_query(array_diff_key(array_filter($parameters, is_string(...)), ['in' => true]));

    return Response::redirect('/c/' . $collection->slug->value . ($back === '' ? '' : '?' . $back));
  }
}
