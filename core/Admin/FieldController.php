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
use Profile\FieldKind;
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
   letting a field out discloses every row's value. A value object is one entry,
   added, moved and removed whole, but let out key by key, since a house may let
   the town out and keep the street. A removal keeps the values, so adding it back
   loses nothing, and a grant's field is a key and not a foreign key, so a rule on
   a removed field fails closed and says nothing, which is why the confirmation
   names the grants first. A field let out, withheld, added or removed is logged
   as a key's arrangement is (`Ingest\ArrangeController`); a move is not, which
   would bury those. */
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
    $entry = $this->entry($type, $key);
    $added = array_map(fn (FieldDefinition $field): CollectionField => new CollectionField($field->key), $entry);
    $this->collections->save($collection->carrying([...$collection->fields, ...$added]));
    $this->logged($collection, 'added', array_map(fn (FieldDefinition $field): string => $field->key, $entry));
    $this->session->keep('fielded', $this->words->get('field.added', ['label' => $this->name($entry)]));

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
      $move === '' ? $fields : $this->moved($type, $fields, $key, $move === 'left' ? -1 : 1)));
    if ($feed !== $held->feed) {
      $this->logged($collection, $feed ? 'let_out' : 'withheld', [$key]);
    }

    return Response::json('{}');
  }

  public function remove(Request $request, string $slug, string $key): Response
  {
    $collection = $this->managed($request, $slug);
    $this->held($collection, $key);
    $entry = $this->entry($this->profile->byType($collection->type), $key);
    $leaving = array_map(fn (FieldDefinition $field): string => $field->key, $entry);
    $this->collections->save($collection->carrying(array_values(array_filter($collection->fields,
      fn (CollectionField $each): bool => !in_array($each->key, $leaving, true)))));
    $this->logged($collection, 'removed', $leaving);
    $this->session->keep('fielded', $this->words->get('field.removed', ['label' => $this->name($entry)]));

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

  /** @return list<FieldDefinition> the field, or every key of the value object it belongs to */
  private function entry(TypeDefinition $type, string $key): array
  {
    $field = $type->field($key) ?? throw EntityNotFound::withId($key);

    return $field->valueObjectOf === null ? [$field] : array_values(array_filter($type->fields(),
      fn (FieldDefinition $each): bool => $each->valueObjectOf === $field->valueObjectOf));
  }

  /** @param list<FieldDefinition> $entry */
  private function name(array $entry): string
  {
    return count($entry) === 1 ? $entry[0]->label : $entry[0]->block ?? $entry[0]->label;
  }

  /** @return list<FieldOffer> */
  private function offers(TypeDefinition $type, Collection $collection): array
  {
    $taken = array_map(fn (CollectionField $each): string => $each->key, $collection->fields);
    $offers = [];
    foreach ($type->suggestions($taken) as $field) {
      $entry = $this->entry($type, $field->key);
      $offers[$entry[0]->key] ??= new FieldOffer($entry[0]->key, $this->name($entry),
        count($entry) === 1 ? $field->kind : FieldKind::Objects,
        $field->valueObjectOf ?? (string) $this->schema->propertyName($field->key), count($entry),
        !$field->writtenBy->hasEditor());
    }

    return array_values($offers);
  }

  /** @return array<string, FieldEntry> by the key of every field the collection carries */
  private function entries(TypeDefinition $type, Collection $collection): array
  {
    $order = $this->grouped($type, $collection->fields);
    $entries = [];
    foreach ($collection->fields as $held) {
      $entry = $this->entry($type, $held->key);
      $at = array_search($this->group($type, $held->key), array_keys($order), true);
      $entries[$held->key] = new FieldEntry(array_map(fn (FieldDefinition $field): string => $field->key, $entry),
        $this->name($entry),
        !array_any($entry, fn (FieldDefinition $field): bool => $field->required),
        $at === 0, $at === count($order) - 1);
    }

    return $entries;
  }

  /**
   * @param list<CollectionField> $fields
   * @return list<CollectionField> the entry holding the key one place further, as a whole
   */
  private function moved(TypeDefinition $type, array $fields, string $key, int $by): array
  {
    $order = $this->grouped($type, $fields);
    $names = array_keys($order);
    $from = (int) array_search($this->group($type, $key), $names, true);
    $to = $from + $by;
    if (isset($names[$to])) {
      [$names[$from], $names[$to]] = [$names[$to], $names[$from]];
    }

    return array_merge(...array_map(fn (string $name): array => $order[$name], $names));
  }

  /**
   * @param list<CollectionField> $fields
   * @return array<string, list<CollectionField>> the entries in order, a value object's keys together
   */
  private function grouped(TypeDefinition $type, array $fields): array
  {
    $order = [];
    foreach ($fields as $held) {
      $order[$this->group($type, $held->key)][] = $held;
    }

    return $order;
  }

  /* Marked, so a value object's property never meets a field's key of the same spelling. */
  private function group(TypeDefinition $type, string $key): string
  {
    $object = $type->field($key)?->valueObjectOf;

    return $object === null ? $key : '@' . $object;
  }
}
