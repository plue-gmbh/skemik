<?php

declare(strict_types=1);

namespace Admin;

/* A record: its screen at `/c/{collection}/{slug}`, the `POST` that makes one,
   and the `PATCH` every save sends; its group is `TranslationController`'s. The
   screen is read by slug, as the feed reads it, within the house's locales, since
   the chrome language is no content locale; the writes take `{id}`, because a
   slug is reusable and a `POST` aimed at one can reach another record than the
   one the operator saw. A save's fields and edges are applied by `FieldEdits` and
   the whole record validated after, a † being a wall on the fields nobody sent
   too, in one transaction with the write, so a term a typed word made leaves with
   a refused save. A lost race is checked first, so nobody fixes a field a
   colleague has since changed. The spine dialog's edits ride the same `PATCH`
   under `spine` (`SpineEdits`): a field may share a spine column's name, an
   offer's `valid_from`, and one save is one version. */

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Domain\Entity;
use Domain\EntityId;
use Domain\Locale;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\StaleWrite;
use Domain\LocalisedText;
use Domain\Props;
use Domain\Relation;
use Domain\Slug;
use Domain\Violation;
use Http\Csrf;
use Http\Preconditions;
use Http\Request;
use Http\Response;
use Profile\Profile;
use Profile\Validator;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\EntityRepository;
use Storage\RelationRepository;
use Storage\SettingStore;
use Storage\Transaction;
use Support\Clock;
use Support\Json;
use Support\Template;
use Support\Uuid;
use Support\Words;

final readonly class RecordController
{
  public function __construct(
    private CollectionController $collections,
    private Frame $frame,
    private RecordPage $page,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private Validator $validator,
    private Showing $showing,
    private Authorizer $authorizer,
    private ActivityLog $log,
    private Sheet $sheet,
    private FieldEdits $fields,
    private SpineEdits $spine,
    private SpineDialog $dialog,
    private RecordWords $recordWords,
    private Profile $profile,
    private SettingStore $settings,
    private Uuid $uuid,
    private Clock $clock,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
    private \PDO $db,
    private Preconditions $preconditions = new Preconditions(),
    private Transaction $transaction = new Transaction(),
    private Json $json = new Json(),
  ) {}

  /* An unknown slug opens the collection, saying so; an unknown collection is
     the collection screen's own 404. */
  public function show(Account $account, Entity $person, Request $request, string $slug, string $record): Response
  {
    $collection = $this->collections->readable($account, $slug);
    $entity = $this->page->find($account, $request, $collection, $record);
    if ($entity === null) {
      return $this->collections->show($account, $person, $request->without('in'), $slug,
        $this->words->get('record.unknown', ['slug' => $record]));
    }
    $columns = $this->collections->columns($account, $collection);

    return $this->frame->around($account, $person, new Screen(
      (new Label())->of($entity, $this->profile->byType($entity->type)),
      $this->page->render($account, $request, $collection, $entity, $columns),
      $this->page->feed($request, $collection, $entity), $collection->slug->value,
      array_any($columns, fn (Column $column): bool => $column->editable)));
  }

  /** @return array{Collection, Entity} */
  private function aimed(Account $account, Request $request, string $slug, string $id): array
  {
    $collection = $this->writing($account, $request, $slug);

    return [$collection, $this->record($collection, $id)];
  }

  private function writing(Account $account, Request $request, string $slug): Collection
  {
    $this->csrf->require($request);
    $collection = $this->collections->readable($account, $slug);
    $this->authorizer->requireHuman(Capability::WriteContent, $collection);

    return $collection;
  }

  /* A view's pending new record, made when it is saved: a draft in the locale
     the view is filtered by, else the house's first, under the first free slug
     its label gives, since custody never arrives with the values, and validated
     whole before it exists. */
  public function create(Account $account, Request $request, string $slug): Response
  {
    $collection = $this->writing($account, $request, $slug);
    try {
      $saved = $this->transaction->saved($this->db,
        fn (): Entity => $this->drafted($account, $request, $collection));
    } catch (SaveRefused $refused) {
      return $this->refused($refused->violations);
    }

    return $this->made($account, $collection, $saved, (string) $request->query('view'));
  }

  private function drafted(Account $account, Request $request, Collection $collection): Entity
  {
    $type = $this->profile->byType($collection->type);
    $id = new EntityId($this->uuid->generate());
    [$props, $edges] = $this->fields->applied($account, $collection, $type, $id, new Props([]), [],
      $this->fields->sent($request));
    SaveRefused::unless($this->validator->check($type, $props->document(), $edges));
    $now = $this->clock->now()->format(Clock::STORED);
    $named = (string) ($props->string($type->namedBy()) ?? $type->name);
    $draft = (new Entity($id, $type->url(), $this->houseLocale($request->query('locale')),
      Slug::from($named, 'record'), new Props([]), $now, $now))->withProps($props);

    return $this->entities->save($draft, $edges);
  }

  /* A locale the house does not hold is refused by name, as the query bar refuses it. */
  private function houseLocale(?string $tag): Locale
  {
    $locales = $this->settings->locales();

    return $tag === null ? $locales[0] : array_find($locales, fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw MalformedValue::named('locale of this house', $tag);
  }

  /* A row answered to a save opens with no query: the page it lands on keeps its own. */
  private function items(Collection $collection): Items
  {
    return new Items($collection, [], $this->dialog->multilingual($collection));
  }

  /* What a save answers, and the row the view it was made in draws in place of its own. */
  private function made(Account $account, Collection $collection, Entity $saved, string $view): Response
  {
    $columns = $this->collections->columns($account, $collection);
    $row = $this->sheet->rows($account, $columns, [$saved])[0];
    $drawn = ['list' => 'list-row', 'cards' => 'card', 'grid' => 'grid-tile', 'timeline' => 'timeline-row'];
    $html = $this->template->render('partials/' . ($drawn[$view] ?? 'sheet-row'), ['words' => $this->words,
      'collection' => $collection, 'columns' => $columns, 'row' => $row, 'ticks' => true,
      'items' => $this->items($collection), 'house' => $this->settings->locales()[0]->tag,
      'glance' => isset($drawn[$view]) ? $this->collections->glance($collection, $columns) : null]);

    return Response::json($this->json->encode([...$this->sheet->saved($row, $columns), 'id' => $saved->id->value,
      'label' => $row->label, 'html' => $html, 'spine' => $this->dialog->row($collection, $row)]), 201)
      ->with(['ETag' => $this->preconditions->etag($saved->version)]);
  }

  public function patch(Account $account, Request $request, string $slug, string $id): Response
  {
    [$collection, $entity] = $this->aimed($account, $request, $slug, $id);
    $expected = $this->preconditions->expectedVersion($request);
    if ($entity->version !== $expected) {
      return $this->stale($account, $request, $collection, $entity);
    }
    try {
      $saved = $this->transaction->saved($this->db,
        fn (): Entity => $this->written($account, $request, $collection, $entity));
    } catch (SaveRefused $refused) {
      return $this->refused($refused->violations);
    } catch (StaleWrite) {
      return $this->stale($account, $request, $collection, $this->entities->find($entity->id));
    }

    return $this->answer($account, $request, $collection, $saved);
  }

  private function written(Account $account, Request $request, Collection $collection, Entity $entity): Entity
  {
    [$edited, $edges] = $this->edited($account, $request, $collection, $entity);
    SaveRefused::unless([...$this->validator->check($this->profile->byType($entity->type),
      $edited->props->document(), $edges), ...$this->showing->kept($edited),
      ...$this->spine->violations($entity, $edited)]);

    return $this->entities->save($edited, $edges, $entity->version);
  }

  /* The record as the `PATCH` leaves it: its fields and edges, a labelled
     thing's words in other locales under `l10n`, then its spine.
     @return array{Entity, list<Relation>} */
  private function edited(Account $account, Request $request, Collection $collection, Entity $entity): array
  {
    $sent = $this->fields->sent($request);
    [$props, $edges] = $this->fields->applied($account, $collection, $this->profile->byType($entity->type),
      $entity->id, $entity->props, $this->relations->outbound($entity->id),
      array_diff_key($sent, ['spine' => true, LocalisedText::KEY => true]));
    [$worded, $edges] = $this->fields->worded($collection, $entity->withProps($props), $edges,
      $sent[LocalisedText::KEY] ?? null);

    return [$this->spine->applied($worded, $sent['spine'] ?? null), $edges];
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

  /** @param list<Violation> $violations */
  private function refused(array $violations): Response
  {
    $said = array_map(fn (Violation $violation): array => ['field' => $violation->field,
      'reason' => $violation->reason === Violation::REQUIRED
        ? $this->words->get('violation.required') : $violation->reason], $violations);

    return Response::json($this->json->encode(['error' => $this->words->get('sheet.refused_record'),
      'violations' => $said]), 422);
  }

  private function stale(Account $account, Request $request, Collection $collection, Entity $current): Response
  {
    $answer = $this->answer($account, $request, $collection, $current);
    $by = $this->log->lastActorName($current->id) ?? $this->words->get('sheet.someone');
    $body = [...$this->json->decode($answer->body), 'error' => $this->words->get('failure.request'), 'by' => $by];

    return new Response(412, $this->json->encode($body), $answer->headers);
  }

  /* Drawn as the screen that sent the save reads the record: in the locale its
     `in` names, a labelled thing's words are that locale's. */
  private function answer(Account $account, Request $request, Collection $collection, Entity $entity): Response
  {
    $columns = $this->collections->columns($account, $collection);
    [, $row, $editing] = $this->recordWords->rows($account, $columns, $entity,
      $this->recordWords->locale($request, $entity));
    $chip = $this->template->render('partials/spine-chip', ['words' => $this->words, 'row' => $row,
      'prefix' => '', 'translations' => $this->items($collection)->own($row), 'opens' => true,
      'current' => $this->settings->locales()[0]->tag]);

    return Response::json($this->json->encode([...$this->sheet->saved($row, $editing), 'chip' => $chip,
      'spine' => $this->dialog->row($collection, $row)]))
      ->with(['ETag' => $this->preconditions->etag($entity->version)]);
  }
}
