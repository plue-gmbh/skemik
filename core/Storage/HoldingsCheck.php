<?php

declare(strict_types=1);

namespace Storage;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\ForbiddenTransition;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\SlugTaken;
use Domain\Exceptions\UnknownPredicate;
use Domain\Exceptions\UnknownType;
use Domain\Exceptions\ValueTaken;
use Domain\LocalisedText;
use Domain\Relation;
use Domain\Violation;
use Profile\Profile;
use Profile\TypeDefinition;
use Profile\Validator;
use Support\Json;
use Support\JsonFailed;

/* The profile and claims tier, the half that needs this house's rows: the shape, the keys,
   the word table, and each row against its type, its spine and its two
   derived columns. Read-only but for the sampled pass's cursor, and it reports:
   nothing here takes an update back, which is `Update\Move`'s shape check
   alone. Beside what no sound install holds, a wrong shape, a dangling edge, a
   row its own type refuses, stands what a release or a rotted derivative leaves
   on a sound one — a † or ! the type gained after rows existed (counted on the
   home screen by `Admin\NeedsLook`), a key the profile no longer names, which the
   `Validator` keeps on save, and `sort_keys`, `words` and the index, which are
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
    private SortKeys $sortKeys = new SortKeys(),
    private Json $json = new Json(),
  ) {}

  /** @return list<Violation> over every row, or over the next `$rows` after the cursor */
  public function run(?int $rows = null): array
  {
    $ids = array_map(fn (string $id): EntityId => new EntityId($id), $this->ids($rows));

    return [...$this->shape(), ...$this->keys(), ...$this->index(), ...$this->rows($ids)];
  }

  /**
   * @param list<EntityId> $ids
   * @return list<Violation>
   */
  public function rows(array $ids): array
  {
    return array_merge(...array_map($this->row(...), $ids));
  }

  /* A row is changed by a save, which the `Validator` checks, and by a restore
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
    return array_map(fn (string $difference): Violation => new Violation('schema', $difference),
      $this->schema->differences($this->db));
  }

  /** @return list<Violation> */
  private function keys(): array
  {
    $violations = [];
    foreach ($this->db->query('pragma foreign_key_check')->fetchAll(\PDO::FETCH_ASSOC) as $key) {
      $reason = 'row ' . $key['rowid'] . ' names a row that is gone';
      $violations[] = new Violation((string) $key['table'], $reason);
    }

    return $violations;
  }

  /* The word table against the `words` it follows, both ways.
     @return list<Violation> */
  private function index(): array
  {
    $rows = 'select e.id, f.key, w.value from entity e, json_each(e.words) f, json_each(f.value) w';
    $held = 'select entity_id, field, word from entity_word';
    $statement = $this->db->prepare(sprintf('select exists (%1$s except %2$s) or exists (%2$s except %1$s)',
      $rows, $held));
    $statement->execute();

    return (int) $statement->fetchColumn() === 0 ? []
      : [new Violation('entity_word', 'disagrees with the words it follows')];
  }

  /* A sampled pass wraps to the start once it reads fewer rows than it asked for.
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
    } catch (MalformedValue|UnknownType|UnknownPredicate|ForbiddenTransition|EntityNotFound|SlugTaken
      |ValueTaken|JsonFailed $failure) {
      return [new Violation('row', $failure->getMessage(), record: $id)];
    }

    return [...$this->againstType($entity, $type, $edges), ...$this->derived($entity, $type)];
  }

  /** @param list<Relation> $edges @return list<Violation> */
  private function againstType(Entity $entity, TypeDefinition $type, array $edges): array
  {
    $document = $entity->props->document();
    $found = array_map(fn (Violation $violation): Violation => new Violation($violation->field,
      $violation->reason, $entity->id), $this->validator->check($type, $document, $edges));
    try {
      $this->collisions->requireUnique($entity, $edges);
    } catch (ValueTaken $taken) {
      $found[] = new Violation($taken->violation->field, $taken->violation->reason, $entity->id);
    }
    foreach (array_keys($document) as $key) {
      if ($key !== LocalisedText::KEY && $type->field((string) $key) === null) {
        $reason = 'a key ' . $type->name . ' does not name';
        $found[] = new Violation((string) $key, $reason, $entity->id);
      }
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
      $found[] = new Violation('sort_keys', 'disagrees with props', $entity->id);
    }
    if ($stored['words'] !== $this->search->words($type, $entity->props)) {
      $found[] = new Violation('words', 'disagrees with props', $entity->id);
    }

    return $found;
  }
}
