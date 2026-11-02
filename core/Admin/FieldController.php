<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\NotPermitted;
use Auth\Session;
use Domain\ActionType;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Props;
use Domain\Slug;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\Reach;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\CollectionField;
use Storage\CollectionStore;
use Storage\HouseItem;
use Storage\HouseObject;
use Support\Words;

/* A collection's fields, for `manageFields`. The dialog opens on the full list,
   since a truncated first screen would be the profile having an opinion about
   which of its fields matter. It asks the write scope, as writing a record does:
   letting a field out discloses every row's value. A key of a value object is a
   field like any other, added, let out, moved and removed alone, so a house may
   keep the town and drop the street; added, it stands after its kin, under their
   heading. A removal keeps the values, so adding it back loses nothing, and a
   grant's field is a key and not a foreign key, so a rule on a removed field
   fails closed and says nothing, which is why the confirmation names the grants
   first. A field let out, withheld, added or removed is logged as a key's
   arrangement is (`Ingest\ArrangeController`); a move is not, which would bury
   those. */
final readonly class FieldController
{
  public function __construct(
    private CollectionStore $collections,
    private Authorizer $authorizer,
    private Profile $profile,
    private SchemaOrg $schema,
    private Session $session,
    private Csrf $csrf,
    private Words $words,
    private ActivityLog $log,
  ) {}

  /**
   * What the collection's screen draws for this account: nothing outside its write scope.
   *
   * @return array{fields: bool, offers: list<FieldOffer>, entries: array<string, FieldEntry>, fielded: string}
   */
  public function screen(Account $account, Collection $collection): array
  {
    $manages = $this->authorizer->arranges($account, $collection);
    $type = $this->profile->byType($collection->type);

    return ['fields' => $manages, 'offers' => $manages ? $this->offers($type, $collection) : [],
      'entries' => $manages ? $this->entries($type, $collection) : [],
      'fielded' => (string) $this->session->take('fielded')];
  }

  public function add(Request $request, string $slug): Response
  {
    $collection = $this->managed($request, $slug);
    $type = $this->profile->byType($collection->type);
    $key = (string) $request->form('key');
    $offered = array_any($this->offers($type, $collection), fn (FieldOffer $offer): bool => $offer->key === $key);
    if (!$offered) {
      throw MalformedValue::named('field ' . $type->name . ' still offers', $key);
    }
    $field = $type->field($key) ?? throw EntityNotFound::withId($key);
    $this->collections->save($collection->carrying($this->placed($type, $collection->fields, $field)));
    $this->logged($collection, 'added', [$key]);
    $this->session->keep('fielded', $this->words->get('field.added', ['label' => $field->label]));

    return $this->backTo($collection, $request);
  }

  /* The body is a form's, not JSON: three strings, and nothing to decode into `mixed`. */
  public function edit(Request $request, string $slug, string $key): Response
  {
    $collection = $this->managed($request, $slug);
    $type = $this->profile->byType($collection->type);
    $held = $this->held($collection, $key);
    parse_str($request->body, $sent);
    $feed = $type->reach === Reach::Public && (is_string($sent['feed'] ?? null) ? $sent['feed'] === '1' : $held->feed);
    $fields = array_map(fn (CollectionField $each): CollectionField => $each->key === $key
      ? new CollectionField($key, $feed) : $each, $collection->fields);
    $move = is_string($sent['move'] ?? null) ? $sent['move'] : '';
    $this->collections->save($collection->carrying(
      $move === '' ? $fields : $this->moved($fields, $key, $move === 'left' ? -1 : 1)));
    if ($feed !== $held->feed) {
      $this->logged($collection, $feed ? 'let_out' : 'withheld', [$key]);
    }

    return Response::json('{}');
  }

  public function remove(Request $request, string $slug, string $key): Response
  {
    $collection = $this->managed($request, $slug);
    $this->held($collection, $key);
    $field = $this->profile->byType($collection->type)->field($key) ?? throw EntityNotFound::withId($key);
    $this->collections->save($collection->carrying(array_values(array_filter($collection->fields,
      fn (CollectionField $each): bool => $each->key !== $key))));
    $this->logged($collection, 'removed', [$key]);
    $this->session->keep('fielded', $this->words->get('field.removed', ['label' => $field->label]));

    return $this->backTo($collection, $request);
  }

  /* Back to the collection under the query the form carried, read as parameters only, so a view narrowed to
     drafts or to one language stays narrowed. */
  private function backTo(Collection $collection, Request $request): Response
  {
    parse_str((string) $request->form('back'), $parameters);
    $back = http_build_query(array_filter($parameters, is_string(...)));

    return Response::redirect('/c/' . $collection->slug->value . ($back === '' ? '' : '?' . $back));
  }

  /** @param list<string> $keys the fields, under the word the log keeps for what was done to them */
  private function logged(Collection $collection, string $done, array $keys): void
  {
    $this->log->record(ActionType::Update, new HouseObject(HouseItem::Collection, $collection->slug->value),
      new Props([$done => $keys]));
  }

  private function managed(Request $request, string $slug): Collection
  {
    $this->csrf->require($request);
    $account = $this->authorizer->requireHuman(Capability::ManageFields);
    try {
      $collection = $this->collections->bySlug(new Slug($slug));
    } catch (MalformedValue) {
      $collection = null;
    }

    if ($collection === null || $this->authorizer->readsAsPublic($account, $collection->type)) {
      throw EntityNotFound::withId($slug);
    }

    return $this->authorizer->writes($account, $collection) ? $collection
      : throw NotPermitted::outsideWriteScope(basename($collection->type));
  }

  private function held(Collection $collection, string $key): CollectionField
  {
    return array_find($collection->fields, fn (CollectionField $each): bool => $each->key === $key)
      ?? throw EntityNotFound::withId($key);
  }

  /** A key of a value object after its kin, so it stands under their heading; any other field at the end.
      @param list<CollectionField> $fields @return list<CollectionField> */
  private function placed(TypeDefinition $type, array $fields, FieldDefinition $added): array
  {
    $object = $added->valueObjectOf;
    $last = $object === null ? null : array_find_key(array_reverse($fields, true),
      fn (CollectionField $each): bool => $type->field($each->key)?->valueObjectOf === $object);
    $field = new CollectionField($added->key);

    return $last === null ? [...$fields, $field]
      : [...array_slice($fields, 0, $last + 1), $field, ...array_slice($fields, $last + 1)];
  }

  /** @return list<FieldOffer> */
  private function offers(TypeDefinition $type, Collection $collection): array
  {
    $taken = array_map(fn (CollectionField $each): string => $each->key, $collection->fields);

    return array_map(fn (FieldDefinition $field): FieldOffer => new FieldOffer($field->key, $field->label,
      $field->kind, (string) $this->schema->parameterName($field->key), !$field->writtenBy->hasEditor()),
      $type->suggestions($taken));
  }

  /** @return array<string, FieldEntry> by the key of every field the collection carries */
  private function entries(TypeDefinition $type, Collection $collection): array
  {
    $entries = [];
    foreach ($collection->fields as $at => $held) {
      $entries[$held->key] = new FieldEntry(!($type->field($held->key)?->required ?? false), $at === 0,
        $at === count($collection->fields) - 1);
    }

    return $entries;
  }

  /**
   * @param list<CollectionField> $fields
   * @return list<CollectionField> the field one place further, where there is one
   */
  private function moved(array $fields, string $key, int $by): array
  {
    $from = (int) array_find_key($fields, fn (CollectionField $each): bool => $each->key === $key);
    $to = $from + $by;
    if (isset($fields[$to])) {
      [$fields[$from], $fields[$to]] = [$fields[$to], $fields[$from]];
    }

    return $fields;
  }
}
