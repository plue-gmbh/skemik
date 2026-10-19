<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\Locale;
use Domain\Status;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Qualifier;
use Profile\Reach;
use Profile\SchemaOrg;
use Profile\Seed\Terms;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\Words;

/* The table's rows and the island's data, for the collection screen and for the
   answer to a save alike, so a row the island wrote comes back drawn by the same
   hand. A select's words come along for every house locale, since a new row is
   written in the locale the table is filtered by whether or not a row of it is
   on the page. The island reads JSON and nothing else: holdings never reach it
   as markup inside its root (`Http\SecurityHeaders`), and every word it shows is
   the chrome's, handed over here, never a literal in `view/scripts/app.js`. */
final readonly class Sheet
{
  private const ISLAND_WORDS = ['sheet.pending', 'sheet.discard', 'sheet.save', 'sheet.saved', 'sheet.discarded',
    'sheet.conflict', 'sheet.reload', 'sheet.overwrite', 'sheet.refused', 'sheet.failed', 'sheet.saving',
    'sheet.empty_option', 'sheet.term_hint', 'sheet.date_shape', 'sheet.unit', 'sheet.amount',
    'cell.yes', 'cell.no', 'duration.unit.Y', 'duration.unit.M', 'duration.unit.W', 'duration.unit.D',
    'duration.unit.H', 'duration.unit.m', 'duration.unit.S', 'duration.years', 'duration.months', 'duration.weeks',
    'duration.days', 'duration.hours', 'duration.minutes', 'duration.seconds', 'record.link_insert',
    'record.link_search', 'record.link', 'record.link_address',
    'record.link_none', 'record.link_done', 'sheet.new', 'cell.gone', 'cell.entries', 'picker.search',
    'picker.add_search', 'picker.none', 'picker.type', 'picker.remove_relation', 'picker.up', 'picker.down',
    'picker.add_term',
    'picker.remove', 'picker.no_targets', 'picker.no_values', 'picker.no_entries', 'picker.add_value',
    'picker.add_entry', 'picker.entry', 'picker.bearing', 'picker.refers', 'picker.unrefer',
    'picker.says', 'picker.done', 'sheet.ticked', 'sheet.ticked_count', 'sheet.ticked_actions', 'sheet.set_status',
    'sheet.set_field', 'sheet.set_to', 'sheet.set_none', 'sheet.set_locale', 'sheet.set_locale_kept', 'sheet.untick',
    'delete.confirm', 'delete.question_many', 'delete.many', 'dialog.cancel'];

  public const OWN_STATUS = ['payment_status', 'order_status', 'reservation_status'];
  private const READ = 'date_read';

  public function __construct(
    private Rows $rows,
    private Cells $cells,
    private CollectionStore $collections,
    private Terms $terms,
    private Profile $profile,
    private SchemaOrg $schema,
    private SettingStore $settings,
    private Clock $clock,
    private Moments $moments,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  /**
   * @param list<Column> $columns
   * @param list<Entity> $records
   * @return list<SheetRow>
   */
  public function rows(Account $account, array $columns, array $records): array
  {
    $held = $this->collections->all();

    return array_map(function (Entity $entity) use ($account, $columns, $held): SheetRow {
      $chips = $this->rows->chips($account, $entity, $held);
      $cells = [];
      foreach ($columns as $column) {
        $key = $column->field->key;
        $cells[$key] = $this->cells->html($entity, $column->field, $chips[$key] ?? []);
      }
      $label = $this->label->of($entity, $this->profile->byType($entity->type));

      return new SheetRow($entity, $label, $this->state($entity), $this->profile->byType($entity->type)->reach,
        $cells, $this->own($entity), $chips, changed: $this->moments->day($entity->updatedAt));
    }, $records);
  }

  /* On the types that cannot publish, the spine's state means nothing to a
     reader; the type's own status property says how the record stands. What
     arrives and is read has no status and says it by a date: only the unread
     one gets the word, so the dot marks what still needs a look. */
  private function own(Entity $entity): ?string
  {
    $type = $this->profile->byType($entity->type);
    if ($type->reach === Reach::Public) {
      return null;
    }
    if ($type->field(self::READ) !== null) {
      return $entity->props->string(self::READ) === null ? $this->words->get('status.unread') : null;
    }
    $key = array_find(self::OWN_STATUS, fn (string $key): bool => $entity->props->string($key) !== null);

    $field = $key === null ? null : $type->field($key);

    return $field === null ? null
      : $this->terms->word($field, (string) $entity->props->string($field->key), $entity->locale);
  }

  public function state(Entity $entity): string
  {
    $now = $this->clock->now();
    if ($entity->status->isPublicAt($now, $entity->publishFrom, $entity->publishTo)) {
      return Status::Published->value;
    }
    if ($entity->status !== Status::Published) {
      return $entity->status->value;
    }
    $ended = $entity->publishTo !== null && $entity->publishTo <= $now->format(Clock::STORED);

    return $ended ? 'ended' : 'scheduled';
  }

  /**
   * @param list<Column> $columns
   * @param list<SheetRow> $rows
   */
  public function data(Collection $collection, array $columns, array $rows, string $csrf): string
  {
    $editable = array_values(array_filter($columns, fn (Column $column): bool => $column->editable));
    $house = $this->settings->locales()[0]->tag;
    $locales = array_map(fn (Locale $locale): string => $locale->tag, $this->settings->locales());

    return (new Json())->encode(['collection' => $collection->slug->value, 'csrf' => $csrf,
      'language' => $this->words->language, 'locale' => $house, 'zone' => $this->clock->zone->getName(),
      'fields' => array_map(fn (Column $column): array => $this->field($column,
        $this->profile->byType($collection->type)), $editable),
      'rows' => array_map(fn (SheetRow $row): array => $this->record($row, $editable), $rows),
      'terms' => (object) array_combine($locales, array_map(fn (string $tag): array => $this->terms($editable, $tag),
        $locales)),
      'words' => (object) array_combine(self::ISLAND_WORDS, array_map($this->word(...), self::ISLAND_WORDS))]);
  }

  /* What the island needs to pick an editor for a field: a select's options, or
     what its input suggests where they are the world's list or a record's kinds,
     a reference's targets — which collections hold them, named in the picker's
     heading — and an object's keys.
     @return array<string, mixed> */
  private function field(Column $column, TypeDefinition $type): array
  {
    $field = $column->field;
    $suggested = $field->key === TypeDefinition::KIND ? $this->schema->kindsOf($type)
      : ($field->list?->codes() ?? []);
    $object = $field->kind === FieldKind::Objects ? $this->profile->valueObject((string) $field->ref) : null;
    $edge = $field->cardinality->isEdge();

    return ['key' => $field->key, 'label' => $column->label,
      'kind' => $field->kind->value, 'options' => $field->options, 'suggested' => $suggested,
      'prose' => $column->prose(), 'many' => $field->holdsMany(),
      'edge' => $edge, 'any' => $field->targets === ['*'],
      'term' => $field->cardinality === Cardinality::T,
      'target' => $edge ? $this->targets($field) : '', 'lines' => $this->lines($field),
      'keys' => $object?->keys ?? [],
      'bearing' => $object?->bearing ?? [], 'reference' => $object?->reference,
      'refers' => $object === null && $this->profile->referring($field) !== null,
      'numbers' => array_keys($object?->kinds ?? [], FieldKind::Number, true)];
  }

  /* The lines a picker offers under a target it adds, by the target's type
     where that changes them and under '' for any other; a term's word is its
     record's.
     @return array<string, array<string, FieldKind>> */
  private function lines(FieldDefinition $field): array
  {
    if ($field->cardinality !== Cardinality::E) {
      return [];
    }
    $predicate = (string) $this->schema->propertyName($field->key);
    $lines = ['' => Qualifier::lines($predicate, null)];
    foreach ($field->targets as $type) {
      $lines[$type] = Qualifier::lines($predicate, $type);
    }

    return $lines;
  }

  private function targets(FieldDefinition $field): string
  {
    if ($field->targets === ['*']) {
      return $this->words->get('picker.any');
    }
    if ($field->cardinality === Cardinality::T) {
      return $this->schema->termSet($field);
    }
    $held = array_filter($this->collections->all(),
      fn (Collection $collection): bool => $this->schema->accepts($field, basename($collection->type)));

    return implode(' · ', array_map(fn (Collection $collection): string => $collection->name, $held))
      ?: implode(' · ', $field->targets);
  }

  /**
   * What a save answers for its record: the new version, and every column redrawn.
   *
   * @param list<Column> $columns
   * @return array{version: int, values: object, cells: array<string, string>}
   */
  public function saved(SheetRow $row, array $columns): array
  {
    $editable = array_values(array_filter($columns, fn (Column $column): bool => $column->editable));

    return ['version' => $row->entity->version, 'values' => $this->record($row, $editable)['values'],
      'cells' => $row->cells];
  }

  /**
   * @param list<Column> $editable
   * @return array{id: string, version: int, locale: string, label: string, values: object, hints?: object}
   */
  private function record(SheetRow $row, array $editable): array
  {
    $values = [];
    foreach ($editable as $column) {
      $key = $column->field->key;
      $values[$key] = match (true) {
        $column->field->cardinality->isEdge() => array_map(fn (Chip $chip, int $index): array
          => $chip->held($index, $row->words), $row->chips[$key] ?? [], array_keys($row->chips[$key] ?? [])),
        $column->field->kind === FieldKind::Objects => $this->entries($row, $column->field),
        isset($row->chips[$key][0]) => $row->chips[$key][0]->held(0),
        default => $this->cells->editable($row->entity, $column->field),
      };
    }

    return ['id' => $row->entity->id->value, 'version' => $row->entity->version,
      'locale' => $row->entity->locale->tag, 'label' => $row->label, 'values' => (object) $values,
      ...($row->authored === null ? [] : ['hints' => $this->hints($row->authored, $editable)])];
  }

  /* The authored words a row editing another locale's holds, by key, as its
     editors' placeholders.
     @param list<Column> $editable */
  private function hints(Entity $authored, array $editable): object
  {
    $hints = [];
    foreach ($editable as $column) {
      $hints[$column->field->key] = $this->cells->editable($authored, $column->field);
    }

    return (object) array_filter($hints,
      fn (string|int|float|bool|array|null $words): bool => !in_array($words, [null, '', []], true));
  }

  /* A value object's rows as their editor takes them, a row's record by the
     row's place, as a held edge is by its own and for the same reason.
     @return list<mixed> */
  private function entries(SheetRow $row, FieldDefinition $field): array
  {
    $entries = (array) $this->cells->editable($row->entity, $field);
    $key = (string) $this->profile->valueObject((string) $field->ref)?->reference;
    foreach ($row->chips[$field->key] ?? [] as $index => $chip) {
      $entries[$index][$key] = ['row' => $index, 'label' => $chip->label, 'glyph' => $chip->glyph,
        'gone' => $chip->gone];
    }

    return $entries;
  }

  /**
   * @param list<Column> $editable
   * @return array<string, string> token => word in that content locale
   */
  private function terms(array $editable, string $tag): array
  {
    $words = [];
    foreach ($editable as $column) {
      foreach ($column->field->choices() as $token) {
        $words[$token] = $this->terms->word($column->field, $token, new Locale($tag));
      }
    }

    return $words;
  }

  /* The placeholders stay in, for the island to fill. */
  private function word(string $key): string
  {
    return $this->words->get($key, ['n' => '{n}', 'label' => '{label}', 'name' => '{name}',
      'field' => '{field}', 'reason' => '{reason}', 'term' => '{term}', 'value' => '{value}', 'tag' => '{tag}']);
  }
}
