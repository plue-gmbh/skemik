<?php

declare(strict_types=1);

namespace Storage;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\ForbiddenTransition;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\SlugTaken;
use Domain\Exceptions\UnknownType;
use Domain\Exceptions\ValueTaken;
use Domain\LocalisedText;
use Domain\Reason;
use Domain\Relation;
use Domain\Violation;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Profile\Validator;
use Support\Json;
use Support\JsonFailed;

/* The profile and claims tier, the half that needs this install's rows: the shape, the keys,
   the word table, and each row against its type, its spine and its two
   derived columns. Read-only but for the sampled pass's cursor, and it reports:
   nothing here takes an update back, which is `Update\Move`'s shape check
   alone. Beside what no sound install holds, a wrong shape, a dangling edge, a
   row its own type refuses, stands what a release or a rotted derivative leaves
   on a sound one — a † or ! the type gained after rows existed (counted on the
   home screen by `Admin\NeedsLook`), a key the profile no longer names, which the
   `Validator` keeps on save, an edge under a predicate it no longer declares,
   which every read leaves out, and `sort_keys`, `words` and the index, which are
   repaired by rewriting. */
final readonly class HoldingsCheck
{
  public function __construct(
    private \PDO $db,
    private Profile $profile,
    private Schema $schema,
    private Validator $validator,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private Collisions $collisions,
    private SettingStore $settings,
    private Search $search,
    private SchemaOrg $schemaOrg,
    private SortKeys $sortKeys = new SortKeys(),
    private Json $json = new Json(),
  ) {}

  /** @return list<Violation> over every row, or over the next `$rows` after the cursor */
  public function run(?int $rows = null): array
  {
    $ids = array_map(fn (string $id): EntityId => new EntityId($id), $this->ids($rows));

    return [...$this->shape(), ...$this->keys(), ...$this->index($ids), ...$this->orphaned(), ...$this->rows($ids)];
  }

  /** Each record lacking a field its type requires, asked of the stored rows one field at a time as the
      `Validator` reads them, a value absent, null, '' or empty and an edge with none under its predicate: a
      release giving a type a † after rows exist is counted at once and not a slice at a time.
      @return list<Violation> */
  public function required(): array
  {
    $found = [];
    foreach ($this->profile->types() as $type) {
      $asked = array_filter($type->fields(), fn (FieldDefinition $field): bool => $field->required
        && ($field->cardinality->isEdge() || $field->writtenBy->isStored()));
      foreach ($asked as $field) {
        $found = [...$found, ...$this->lacking($type, $field)];
      }
    }

    return $found;
  }

  /** @return list<Violation> */
  private function lacking(TypeDefinition $type, FieldDefinition $field): array
  {
    $edge = $field->cardinality->isEdge();
    $missing = $edge ? 'json_type(e.props, :key) is null and not exists (select 1 from relation r'
      . ' where r.subject_id = e.id and r.predicate = :predicate)'
      : "case json_type(e.props, :key) when 'text' then json_extract(e.props, :key) = ''"
      . " when 'array' then json_array_length(e.props, :key) = 0 when 'object' then json_extract(e.props, :key) = '{}'"
      . " else coalesce(json_type(e.props, :key), 'null') = 'null' end";
    $statement = $this->db->prepare("select e.id from entity e where e.type = :type and ($missing)");
    $statement->execute(['type' => $type->url(), 'key' => '$.' . $field->key,
      ...($edge ? ['predicate' => (string) $this->schemaOrg->propertyName($field->key)] : [])]);

    return array_map(fn (string $id): Violation => new Violation($field->key, Reason::Required,
      record: new EntityId($id)), $statement->fetchAll(\PDO::FETCH_COLUMN));
  }

  /**
   * @param list<EntityId> $ids
   * @return list<Violation>
   */
  public function rows(array $ids): array
  {
    return array_merge(...array_map($this->row(...), $ids));
  }

  /** A row is changed by a save, which the `Validator` checks, and by a restore
      or a purge taking its edge, which it does not, so a changed row is read
      again rather than taken as mended.
      @param list<EntityId> $ids
      @return list<EntityId> those changed at or after `$since` */
  public function changed(array $ids, string $since): array
  {
    $statement = $this->db->prepare('select id from entity where updated_at >= :since'
      . ' and id in (select value from json_each(:ids))');
    $statement->execute(['since' => $since,
      'ids' => $this->json->encode(array_map(fn (EntityId $id): string => $id->value, $ids))]);

    return array_map(fn (string $id): EntityId => new EntityId($id),
      $statement->fetchAll(\PDO::FETCH_COLUMN));
  }

  /** @return list<Violation> */
  private function shape(): array
  {
    return array_map(fn (string $difference): Violation => Violation::stated('schema', $difference),
      $this->schema->differences($this->db));
  }

  /** @return list<Violation> */
  private function keys(): array
  {
    $violations = [];
    foreach ($this->db->query('pragma foreign_key_check')->fetchAll(\PDO::FETCH_ASSOC) as $key) {
      $reason = 'row ' . $key['rowid'] . ' names a row that is gone';
      $violations[] = Violation::stated((string) $key['table'], $reason);
    }

    return $violations;
  }

  /** The word table against the `words` it follows, both ways, for the records read, each that differs named, so
      the next slice keeps it: compared whole, it took seconds per slice on a grown install. Keyed by word,
      `entity_word` gives up a slice's rows in one scan.
      @param list<EntityId> $ids
      @return list<Violation> */
  private function index(array $ids): array
  {
    $slice = 'select value from json_each(:ids)';
    $rows = 'select e.id as id, f.key, w.value, count(*) from entity e, json_each(e.words) f,'
      . " json_each(f.value) w where e.id in ($slice) group by e.id, f.key, w.value";
    $held = 'select r.entity_id as id, n.name, x.word, x.times from entity_word x'
      . ' join word_record r on r.number = x.record join word_field n on n.number = x.field'
      . " where x.record in (select number from word_record where entity_id in ($slice))";
    $statement = $this->db->prepare(sprintf('select id from (%1$s except %2$s) union select id from (%2$s except %1$s)',
      $rows, $held));
    $statement->execute(['ids' => $this->json->encode(array_map(fn (EntityId $id): string => $id->value, $ids))]);

    $stale = $statement->fetchAll(\PDO::FETCH_COLUMN);

    return array_map(fn (string $id): Violation => new Violation('entity_word', Reason::Stale,
      record: new EntityId($id)), $stale);
  }

  /** Word rows whose record is gone, which belong to no slice and so are asked of the whole table.
      @return list<Violation> */
  private function orphaned(): array
  {
    $gone = $this->db->query('select exists (select 1 from entity_word x where x.record not in'
      . ' (select number from word_record)) or exists (select 1 from word_record r'
      . ' where not exists (select 1 from entity e where e.id = r.entity_id))')->fetchColumn();

    return (int) $gone === 0 ? [] : [new Violation('entity_word', Reason::Stale)];
  }

  /** A sampled pass wraps to the start once it reads fewer rows than it asked for.
      @return list<string> */
  private function ids(?int $rows): array
  {
    $statement = $this->db->prepare('select id from entity where id > :cursor'
      . ' order by id limit :rows');
    $statement->bindValue('cursor', $rows === null ? '' : ($this->settings->holdingsCursor() ?? ''));
    $statement->bindValue('rows', $rows ?? -1, \PDO::PARAM_INT);
    $statement->execute();
    $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);
    if ($rows !== null) {
      $this->settings->setHoldingsCursor(count($ids) < $rows ? null : $ids[count($ids) - 1]);
    }

    return $ids;
  }

  /** @return list<Violation> */
  private function row(EntityId $id): array
  {
    try {
      $entity = $this->entities->find($id);
      $type = $this->profile->byType($entity->type);
      $edges = $this->relations->outbound($id);
      /* A stored row's state is asked here: `Entity` has no type in reach. */
      $entity->status->to($entity->status, $type);
      $this->collisions->requireFreeSlug($entity);
      $this->collisions->requireFreeTranslation($entity);
    } catch (MalformedValue|UnknownType|ForbiddenTransition|EntityNotFound|SlugTaken|ValueTaken
      |JsonFailed $failure) {
      return [Violation::stated('row', $failure->getMessage(), $id)];
    }

    return [...$this->againstType($entity, $type, $edges), ...$this->derived($entity, $type)];
  }

  /** @param list<Relation> $edges @return list<Violation> */
  private function againstType(Entity $entity, TypeDefinition $type, array $edges): array
  {
    $document = $entity->props->document();
    $named = ['type' => $type->name];
    $found = array_map(fn (Violation $violation): Violation => new Violation($violation->field,
      $violation->reason, $violation->values, $entity->id), $this->validator->check($type, $document, $edges));
    try {
      $this->collisions->requireUnique($entity, $edges);
    } catch (ValueTaken $taken) {
      $found[] = new Violation($taken->violation->field, $taken->violation->reason, $taken->violation->values,
        $entity->id);
    }
    foreach (array_keys($document) as $key) {
      if ($key !== LocalisedText::KEY && $type->field((string) $key) === null) {
        $found[] = new Violation((string) $key, Reason::KeyNotNamed, $named, $entity->id);
      }
    }
    foreach ($this->relations->strays($entity->id) as $predicate) {
      $found[] = new Violation($predicate, Reason::EdgeNotNamed, $named, $entity->id);
    }

    return $found;
  }

  /** @return list<Violation> */
  private function derived(Entity $entity, TypeDefinition $type): array
  {
    $statement = $this->db->prepare('select sort_keys, words from entity where id = :id');
    $statement->execute(['id' => $entity->id->value]);
    $stored = $statement->fetch(\PDO::FETCH_ASSOC);
    $found = [];
    if ($stored['sort_keys'] !== $this->sortKeys->of($type, $entity->props)) {
      $found[] = new Violation('sort_keys', Reason::Stale, record: $entity->id);
    }
    if ($stored['words'] !== $this->search->words($type, $entity->props, $entity->slug)) {
      $found[] = new Violation('words', Reason::Stale, record: $entity->id);
    }

    return $found;
  }
}
