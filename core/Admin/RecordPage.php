<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRepository;
use Auth\Capability;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Slug;
use Domain\Status;
use Domain\TranslationGroup;
use Http\Csrf;
use Http\Request;
use Http\Upload;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\SettingStore;
use Support\Template;
use Support\Words;

/* What the record screen reads besides the record: which record a slug opens,
   what points at it, its translations, and its neighbours in the collection's
   query. Every record it names passes the reader's scope first (`Rows`), so a
   count or a row never discloses what the record itself would not. */
final readonly class RecordPage
{
  public function __construct(
    private EntityRepository $entities,
    private Showing $showing,
    private CollectionStore $collections,
    private SettingStore $settings,
    private QueryBar $bar,
    private Sheet $sheet,
    private Rows $rows,
    private Prose $prose,
    private SpineDialog $dialog,
    private RecordWords $recordWords,
    private AccountRepository $accounts,
    private Profile $profile,
    private SchemaOrg $schema,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
    private Grants $grants,
    private LiveKeys $keys,
    private Label $label = new Label(),
  ) {}

  /** @param list<Column> $columns */
  public function render(Account $account, Request $request, Collection $collection, Entity $entity,
    array $columns): string
  {
    $type = $this->profile->byType($entity->type);
    $query = http_build_query(array_diff_key($request->query, ['in' => true, 'words' => true]));
    $locale = $this->recordWords->locale($request, $entity);
    [$row, $edit, $editing] = $this->recordWords->rows($account, $columns, $entity, $locale);
    $pointers = $this->pointers($account, $entity, $locale ?? $entity->locale);

    return $this->template->render('pages/record', ['words' => $this->words, 'collection' => $collection,
      'row' => $row, 'edit' => $edit, 'blocks' => FieldBlock::of($columns), 'editing' => FieldBlock::of($editing),
      'naming' => $type->namedBy(), 'csrf' => $this->csrf->token(), 'query' => $query,
      'offered' => $this->recordWords->offered($entity), 'locale' => $locale, 'authored' => $entity,
      'chips' => $this->rows->chips($account, $entity, $this->collections->all()),
      'prose' => $this->prose($account, $row->entity, $columns),
      'pointers' => $pointers,
      'pointing' => array_sum(array_map(fn (Pointer $pointer): int => $pointer->records(), $pointers)),
      'translations' => $this->dialog->translations($account, $collection, $entity),
      'asset' => $type->name === 'MediaObject', 'copies' => $type->copies(),
      'limit' => Intake::size(Upload::limit()),
      'neighbours' => $this->neighbours($account, $request, $collection, $entity),
      'writes' => array_any($columns, fn (Column $column): bool => $column->editable),
      'held' => $this->accounts->heldBy($entity->id), 'manages' => $account->can(Capability::ManageAccounts),
      'badge' => $this->badge($account, $collection, $entity),
      'labels' => $this->keys->labels(), 'until' => $this->keys->until(),
      'back' => $request->path . ($request->query === [] ? '' : '?' . http_build_query($request->query)),
      'public' => in_array(Status::Published, Status::heldBy($type), true),
      'data' => $this->sheet->data($collection, $editing, [$edit], $this->csrf->token()),
      'spine' => $this->dialog->data($collection, [$edit])]);
  }

  /* The grant for this record alone, for whoever issues one. */
  private function badge(Account $account, Collection $collection, Entity $entity): ?Badge
  {
    return $account->can(Capability::ManageTokens) ? $this->grants->record($account, $collection, $entity) : null;
  }

  /* The record's address in the locale its words are shown in, on the route a
     work of that locale answers at. */
  public function feed(Request $request, Collection $collection, Entity $entity): FeedPanel
  {
    $locale = $this->recordWords->locale($request, $entity) ?? $entity->locale;

    return FeedPanel::at($request, sprintf('/feed/%s/%s/%s', $locale->tag, $collection->slug->value,
      $entity->slug->value), true);
  }

  /* Each prose field read as the page reads it, by key.
     @param list<Column> $columns
     @return array<string, string> */
  private function prose(Account $account, Entity $entity, array $columns): array
  {
    $prose = [];
    foreach ($columns as $column) {
      $source = $column->prose() ? $entity->props->string($column->field->key) : null;
      if ($source !== null && trim($source) !== '') {
        $prose[$column->field->key] = $column->field->kind === FieldKind::Markdown
          ? $this->prose->markdown($account, $source) : $this->prose->text($source);
      }
    }

    return $prose;
  }

  /* The locale the link named first, then the house's locales in order: the
     admin path carries no locale, so the screen says which one it opened. */
  public function find(Account $account, Request $request, Collection $collection, string $slug): ?Entity
  {
    try {
      $address = new Slug($slug);
    } catch (MalformedValue) {
      return null;
    }
    $locales = $this->settings->locales();
    $named = array_filter($locales, fn (Locale $locale): bool => $locale->tag === $request->query('in'));
    foreach ([...$named, ...$locales] as $locale) {
      $entity = $this->entities->bySlug($collection->type, $locale, $address);
      if ($entity !== null && $this->rows->readable($account, $entity)) {
        return $entity;
      }
    }

    return null;
  }

  /* A work's translations pointing with one field are one row, named by the one
     in the locale the screen reads, else by the one the others translate.
     @return list<Pointer> each named in the words of the locale the screen reads */
  public function pointers(Account $account, Entity $entity, Locale $in): array
  {
    $members = [];
    $predicates = [];
    foreach ($this->showing->edges($entity) as $edge) {
      $subject = $this->readable($account, $edge->subject);
      if ($subject !== null) {
        $group = TranslationGroup::rootOf($subject)->value . ' ' . $edge->predicate;
        $members[$group][$subject->id->value] = $subject;
        $predicates[$group] = $edge->predicate;
      }
    }

    return array_values(array_map(fn (string $group): Pointer => $this->pointer(array_values($members[$group]),
      $predicates[$group], $in), array_keys($members)));
  }

  /* The records before and after this one in the query it was opened from, on
     its page or across the page's edge; null at either end.
     @return array{?string, ?string} */
  public function neighbours(Account $account, Request $request, Collection $collection, Entity $entity): array
  {
    $query = $this->bar->query($account, $request->without('in'), $collection);
    $ids = $query === null ? [] : $this->page($query, $query->page);
    $index = array_search($entity->id->value, array_keys($ids), true);
    if ($query === null || $index === false) {
      return [null, null];
    }
    $before = $index > 0 ? array_values($ids)[$index - 1]
      : ($query->page > 1 ? $this->last($this->page($query, $query->page - 1)) : null);
    $after = $index < count($ids) - 1 ? array_values($ids)[$index + 1]
      : (count($ids) === $query->perPage ? $this->first($this->page($query, $query->page + 1)) : null);
    $carried = fn (?array $at): ?string => $at === null ? null : Rows::address($collection, $at[0],
      http_build_query([...array_diff_key($request->query, ['in' => true]), 'page' => (string) $at[1]]));

    return [$carried($before), $carried($after)];
  }

  /** @return array<string, array{Entity, int}> by id, each with its page */
  private function page(Query $query, int $page): array
  {
    $paged = new Query($query->type, $query->filters, $query->statuses, $query->locale, $query->sort,
      $query->descending, $query->search, $page, $query->perPage, $query->searchIn, $query->searchThrough);
    $ids = [];
    foreach ($this->entities->list($paged) as $entity) {
      $ids[$entity->id->value] = [$entity, $page];
    }

    return $ids;
  }

  /**
   * @param array<string, array{Entity, int}> $ids
   * @return ?array{Entity, int}
   */
  private function first(array $ids): ?array
  {
    return $ids === [] ? null : array_values($ids)[0];
  }

  /**
   * @param array<string, array{Entity, int}> $ids
   * @return ?array{Entity, int}
   */
  private function last(array $ids): ?array
  {
    return $ids === [] ? null : array_values($ids)[count($ids) - 1];
  }

  private function readable(Account $account, EntityId $id): ?Entity
  {
    try {
      $entity = $this->entities->find($id);
    } catch (EntityNotFound) {
      return null;
    }

    return $entity->deletedAt === null && $this->rows->readable($account, $entity) ? $entity : null;
  }

  /** @param list<Entity> $members one record, or the translations of one work */
  private function pointer(array $members, string $predicate, Locale $in): Pointer
  {
    $subject = array_find($members, fn (Entity $member): bool => $member->locale->tag === $in->tag)
      ?? array_find($members, fn (Entity $member): bool => $member->translationOf === null) ?? $members[0];
    $holder = array_find($this->collections->all(),
      fn (Collection $collection): bool => $collection->type === $subject->type);
    $type = $this->profile->byType($subject->type);
    $field = array_find($type->edges(),
      fn (FieldDefinition $edge): bool => $this->schema->propertyName($edge->key) === $predicate);
    $named = (new LocalisedText($type))->shown($subject, $in);

    $records = $this->locales($members, $holder);
    $words = $records === [] && $type->locale->localises() ? $this->worded($subject, $holder, $type) : [];

    return new Pointer(SidebarEntry::glyphOf($subject->type), $this->label->of($named, $type),
      $this->sheet->state($subject), $this->words->get('type_one.' . $type->name), $field?->label ?? $predicate,
      $holder === null ? null : Rows::address($holder, $subject), $records ?: $words, $words !== []);
  }

  /* A labelled thing's languages, the authored one and each holding words of its own, every one opening
     the record on its words, since what points here reads it in each of them.
     @return array<string, string> */
  private function worded(Entity $subject, ?Collection $holder, TypeDefinition $type): array
  {
    $held = (new LocalisedText($type))->locales($subject);
    if ($holder === null || $held === []) {
      return [];
    }
    $locales = [];
    foreach ($this->settings->locales() as $locale) {
      $own = $locale->tag === $subject->locale->tag;
      if ($own || in_array($locale->tag, $held, true)) {
        $locales[$locale->tag] = Rows::address($holder, $subject, $own ? '' : 'words=' . $locale->tag);
      }
    }

    return $locales;
  }

  /* Each translation's address in the house's order of its locales.
     @param list<Entity> $members
     @return array<string, string> */
  private function locales(array $members, ?Collection $holder): array
  {
    if (count($members) < 2 || $holder === null) {
      return [];
    }
    $order = array_map(fn (Locale $locale): string => $locale->tag, $this->settings->locales());
    usort($members, fn (Entity $one, Entity $other): int => array_search($one->locale->tag, $order, true)
      <=> array_search($other->locale->tag, $order, true));
    $locales = [];
    foreach ($members as $member) {
      $locales[$member->locale->tag] = Rows::address($holder, $member);
    }

    return $locales;
  }
}
