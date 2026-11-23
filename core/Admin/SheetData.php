<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\Locale;
use Http\Upload;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Qualifier;
use Profile\SchemaOrg;
use Profile\Seed\Terms;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\Words;

/* The island's data, for the collection screen, the record screen and the answer
   to a save. A select's words come along in the chrome language, whatever the
   language of the row they are chosen in. The island reads JSON and nothing else:
   holdings never reach it as markup inside its root (`Http\SecurityHeaders`),
   and every word it shows is the chrome's, handed over here, never a literal in
   `view/scripts/app.js`. */
final readonly class SheetData
{
  private const ISLAND_WORDS = ['sheet.pending', 'sheet.discard', 'sheet.save', 'sheet.save_short', 'sheet.saved',
    'sheet.discarded', 'sheet.search_held', 'sheet.conflict', 'sheet.reload', 'sheet.overwrite', 'sheet.refused',
    'sheet.failed', 'sheet.saving', 'sheet.empty_option', 'sheet.term_hint', 'sheet.date_shape', 'sheet.unit',
    'sheet.amount', 'sheet.now', 'sheet.date_or_time_shape',
    'cell.yes', 'cell.no', 'duration.unit.Y', 'duration.unit.M', 'duration.unit.W', 'duration.unit.D',
    'duration.unit.H', 'duration.unit.m', 'duration.unit.S', 'duration.years', 'duration.months', 'duration.weeks',
    'duration.days', 'duration.hours', 'duration.minutes', 'duration.seconds', 'record.link_insert',
    'record.link_search', 'record.link', 'record.link_address', 'picker.add_term', 'picker.refers_upload',
    'record.link_none', 'record.link_done', 'sheet.new', 'cell.gone', 'cell.entries', 'picker.search',
    'picker.add_search', 'picker.none', 'picker.type', 'picker.remove_relation', 'picker.up', 'picker.down',
    'picker.remove', 'picker.no_targets', 'picker.no_values', 'picker.no_entries', 'picker.add_value',
    'picker.add_entry', 'picker.entry', 'picker.bearing', 'picker.refers', 'picker.refers_file', 'picker.unrefer',
    'picker.says', 'picker.done', 'sheet.ticked', 'sheet.ticked_count', 'sheet.ticked_actions', 'sheet.set_status',
    'sheet.set_field', 'sheet.set_to', 'sheet.set_none', 'sheet.set_locale', 'sheet.set_locale_kept', 'sheet.untick',
    'delete.confirm', 'delete.question', 'delete.one', 'delete.question_many', 'delete.many', 'dialog.cancel',
    'media.upload', 'media.upload_up_to', 'media.sending', 'media.lost', 'picker.draft', 'picker.link_file'];

  public function __construct(
    private Cells $cells,
    private CollectionStore $collections,
    private Terms $terms,
    private Profile $profile,
    private SchemaOrg $schema,
    private SettingStore $settings,
    private Clock $clock,
    private Words $words,
  ) {}

  /**
   * @param list<Column> $columns
   * @param list<SheetRow> $rows
   */
  public function data(Collection $collection, array $columns, array $rows, string $csrf): string
  {
    $editable = array_values(array_filter($columns, fn (Column $column): bool => $column->editable));
    $firstLocale = $this->settings->locales()[0]->tag;

    return (new Json())->encode(['collection' => $collection->slug->value, 'csrf' => $csrf,
      'language' => $this->words->language, 'locale' => $firstLocale, 'zone' => $this->clock->zone->getName(),
      'limit' => Intake::size(Upload::limit()),
      'fields' => array_map(fn (Column $column): array => $this->field($column,
        $this->profile->byType($collection->type)), $editable),
      'rows' => array_map(fn (SheetRow $row): array => $this->record($row, $editable), $rows),
      'terms' => (object) $this->terms($editable),
      'words' => (object) array_combine(self::ISLAND_WORDS, array_map($this->word(...), self::ISLAND_WORDS))]);
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

  /** What the island needs to pick an editor for a field: a select's options, or
      what its input suggests where they are the world's list or a record's kinds,
      a reference's targets — which collections hold them, named in the picker's
      heading — and whether it uploads and which kinds of file, which of its keys
      take a file of the library as a link, and an object's keys, their kinds
      where they are not text, those that take a closed list, and those a cell reads.
      @return array<string, mixed> */
  private function field(Column $column, TypeDefinition $type): array
  {
    $field = $column->field;
    $suggested = $field->key === TypeDefinition::KIND ? $this->schema->kindsOf($type)
      : ($field->list?->codes() ?? []);
    $object = $field->kind === FieldKind::Objects ? $this->profile->valueObject((string) $field->ref) : null;
    $keys = $object === null ? [] : $field->carried($object);
    $edge = $field->cardinality->isEdge();

    return ['key' => $field->key, 'label' => $column->label,
      'kind' => $field->kind->value, 'options' => $field->options, 'suggested' => $suggested,
      'prose' => $column->prose(), 'many' => $field->holdsMany(), 'whole' => $field->whole,
      'edge' => $edge, 'any' => $field->targets === ['*'], 'upload' => $column->uploads,
      'accept' => $this->schema->fileKinds($object?->targets ?? $field->targets),
      'term' => $field->cardinality === Cardinality::T, 'files' => (object) $this->schema->fileKeys($field, $object),
      'target' => $edge ? $this->targets($field) : '', 'lines' => $this->lines($field),
      'keys' => $keys, 'bearing' => $object?->bearing ?? [], 'said' => $object?->said ?? [],
      'reference' => $object?->reference,
      'refers' => $object === null && $this->profile->referring($field) !== null,
      'kinds' => (object) array_map(fn (FieldKind $kind): string => $kind->value, $object?->kinds ?? []),
      'choices' => (object) $this->terms->rowOptions($field)];
  }

  /** The lines a picker offers under a target it adds, by the target's type
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

  /** The authored words a row editing another locale's holds, by key, as its
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

  /** A value object's rows as their editor takes them, a row's record by the
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
   * @return array<string, string> token => word
   */
  private function terms(array $editable): array
  {
    $words = [];
    foreach ($editable as $column) {
      foreach ($this->terms->tokens($column->field) as $token) {
        $words[$token] = $this->terms->word($column->field, $token, new Locale($this->words->language));
      }
    }

    return $words;
  }

  /* The placeholders stay in, for the island to fill. */
  private function word(string $key): string
  {
    return $this->words->get($key, ['n' => '{n}', 'label' => '{label}', 'name' => '{name}',
      'field' => '{field}', 'reason' => '{reason}', 'term' => '{term}', 'value' => '{value}', 'tag' => '{tag}',
      'limit' => '{limit}', 'upload' => '{upload}']);
  }
}
