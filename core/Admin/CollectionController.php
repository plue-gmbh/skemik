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
use Domain\Locale;
use Domain\Slug;
use Feed\PublicFilter;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Storage\Collection;
use Storage\CollectionField;
use Storage\CollectionStore;
use Storage\CollectionView;
use Storage\EntityRepository;
use Storage\Query;
use Storage\SettingStore;
use Support\Clock;
use Support\Template;
use Support\Words;

/* One collection in its view. The query bar is the same query the feed panel
   shows as an address, less what a stranger is refused, parsed by `Feed\FeedRequest` and run by `Storage\Query`
   for the editor as for `GET /feed/{locale}/{collection}`, which makes every
   field's being filterable visible rather than merely true. Never public is a tag
   and not a toggle, since there is nothing to switch. The chosen view is kept per
   collection in the session, not in browser storage, which a server-rendered page
   cannot read before it has drawn the wrong view. */
final readonly class CollectionController
{
  public function __construct(
    private CollectionStore $collections,
    private EntityRepository $entities,
    private Authorizer $authorizer,
    private PublicFilter $filter,
    private Profile $profile,
    private SchemaOrg $schema,
    private Sheet $sheet,
    private SpineDialog $dialog,
    private QueryBar $bar,
    private MediaController $media,
    private FieldController $fields,
    private SettingStore $settings,
    private Frame $frame,
    private Session $session,
    private Grants $grants,
    private LiveKeys $keys,
    private Clock $clock,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
    private Rows $rows,
  ) {}

  /* A notice is a sentence the screen opens with, like an unknown record's. */
  public function show(Account $account, Entity $person, Request $request, string $slug,
    string $notice = ''): Response
  {
    $collection = $this->readable($account, $slug);
    $columns = $this->columns($account, $collection);
    $islands = array_any($columns, fn (Column $column): bool => $column->editable);
    $main = $this->template->render('pages/collection', [...$this->view($account, $request, $collection, $columns),
      'words' => $this->words, 'collection' => $collection, 'columns' => $columns,
      'creates' => $islands,
      'public' => $this->filter->passesType($collection->type), 'trashed' => $this->trashed(),
      'trash' => $this->trash($account, $collection),
      'badges' => $this->grants->badges($account, $collection, $columns, $account->can(Capability::ManageTokens)),
      'labels' => $this->keys->labels(), 'until' => $this->keys->until(), 'back' => $this->back($request),
      'house' => $this->settings->locales()[0]->tag,
      ...$this->fields->screen($account, $collection),
      'csrf' => $this->csrf->token()]);

    return $this->frame->around($account, $person, new Screen($collection->name, $main,
      $this->feed($request, $collection), $collection->slug->value, $islands, $notice));
  }

  public function feed(Request $request, Collection $collection): FeedPanel
  {
    $asked = $request->query('locale');
    $locale = array_find($this->settings->locales(), fn (Locale $each): bool => $each->tag === $asked)
      ?? $this->settings->locales()[0];
    $query = array_filter($request->without(...QueryBar::OWN)->query,
      fn (string $name): bool => $this->strangerAsks($name, $request, $collection), ARRAY_FILTER_USE_KEY);

    return FeedPanel::at($request, sprintf('/feed/%s/%s%s', $locale->tag, $collection->slug->value,
      $query === [] ? '' : '?' . http_build_query($query)), true);
  }

  /* A stranger is refused a sort or a filter on a field the house withholds, so
     the address carries neither: what the colleague sorts by need not leave. */
  private function strangerAsks(string $name, Request $request, Collection $collection): bool
  {
    $field = $name === 'sort' ? ltrim((string) $request->query('sort'), '-') : $name;
    if (in_array($name, ['q', 'page', 'per_page'], true) || $field === 'slug') {
      return true;
    }
    $names = array_map(fn (CollectionField $each): ?string => $this->schema->parameterName($each->key),
      $this->filter->fields($collection));

    return in_array($field, $names, true);
  }

  /**
   * The view and the query bar over it, under the query the address asks.
   *
   * @param list<Column> $columns
   * @return array<string, object|array<array-key, object|string|int|float|bool|null>|string|int|bool|null>
   */
  private function view(Account $account, Request $request, Collection $collection, array $columns): array
  {
    $view = $this->drawn($request, $collection);
    $glance = $view === CollectionView::Table ? null : $this->glance($collection, $columns);
    $request = $this->ordered($request, $view, $glance?->date());
    $query = $this->bar->query($account, $request, $collection);
    $rows = $this->sheet->rows($account, $columns, $query === null ? [] : $this->entities->list($query));
    $library = basename($collection->type) === 'MediaObject'
      ? $this->media->library($account, $collection, $rows) : [];

    return [...$library, 'view' => $view, 'glance' => $glance,
      'timeline' => new Timeline($glance?->date(), $this->clock, $this->words->language),
      'fixed' => $view === CollectionView::Timeline,
      'rows' => $rows, 'page' => $query->page ?? 1, 'perPage' => $query->perPage ?? Query::PER_PAGE,
      'items' => new Items($collection, $request->query, $this->dialog->multilingual($collection)),
      'parameters' => $request->query, 'narrowing' => $this->bar->narrowing($request),
      ...$this->counted($account, $collection, $query),
      'narrowed' => $this->bar->narrowing($request) !== [] || (string) $request->query('q') !== '',
      'filters' => $this->bar->filters($account, $collection, $columns),
      'data' => $this->sheet->data($collection, $columns, $rows, $this->csrf->token()),
      'spine' => $this->dialog->data($collection, $rows)];
  }

  /* What the page counts and what the whole reading does: the same question where the page's
     query asks nothing beyond its order and page, so it is asked once and not twice.
     @return array{total: int, all: int} */
  private function counted(Account $account, Collection $collection, ?Query $query): array
  {
    $total = $query === null ? 0 : $this->entities->count($query);
    $whole = $query !== null && $query->filters === [] && $query->statuses === [] && $query->locale === null
      && $query->search === '';

    return ['total' => $total, 'all' => $whole ? $total : $this->bar->total($account, $collection)];
  }

  /* The view asked for, which the session then keeps; else the one kept, else the table. */
  private function drawn(Request $request, Collection $collection): CollectionView
  {
    $kept = 'view.' . $collection->slug->value;
    $asked = CollectionView::tryFrom((string) $request->query('view'));
    if ($asked !== null) {
      $this->session->remember($kept, $asked->value);
    }

    return $asked ?? CollectionView::tryFrom((string) $this->session->remembered($kept)) ?? CollectionView::Table;
  }

  /* A timeline is ordered by its date, newest first unless it asks the other way,
     whatever order the view it was switched from had; without a date, by when
     each record was made, newest first, which is a list asked for no order. */
  private function ordered(Request $request, CollectionView $view, ?Column $dated): Request
  {
    $date = $dated?->parameter;

    return match (true) {
      $view !== CollectionView::Timeline => $request,
      $date === null => $request->without('sort'),
      ltrim((string) $request->query('sort'), '-') === $date => $request,
      default => $request->with('sort', '-' . $date),
    };
  }

  private function back(Request $request): string
  {
    return $request->path . ($request->query === [] ? '' : '?' . http_build_query($request->query));
  }

  /* The records a delete just moved to the trash, offered back once: deleting
     is immediate rather than pending, so the undo is where it lands.
     @return list<SheetRow> */
  private function trashed(): array
  {
    $rows = [];
    foreach (array_filter(explode(',', (string) $this->session->take('trashed'))) as $id) {
      try {
        $entity = $this->entities->find(new EntityId($id));
      } catch (EntityNotFound) {
        continue;
      }
      $type = $this->profile->byType($entity->type);
      if ($entity->deletedAt !== null) {
        $rows[] = new SheetRow($entity, (new Label())->of($entity, $type), 'trashed', $type->reach, []);
      }
    }

    return $rows;
  }

  /* How many records wait in this collection's trash, for whoever can take one
     out; null draws no way in. */
  private function trash(Account $account, Collection $collection): ?int
  {
    return $this->rows->opensTrash($account, $collection)
      ? $this->entities->count(new Query($collection->type)->inTrash()) : null;
  }

  /* An unknown collection and one this account cannot read at all answer alike,
     as the sidebar that does not list it would suggest. */
  public function readable(Account $account, string $slug): Collection
  {
    try {
      $collection = $this->collections->bySlug(new Slug($slug));
    } catch (MalformedValue) {
      $collection = null;
    }
    $closed = $collection !== null && $this->authorizer->readsAsPublic($account, $collection->type)
      && !$this->filter->passesType($collection->type);

    return $collection === null || $closed ? throw EntityNotFound::withId($slug) : $collection;
  }

  /* What the views beside the table show of a row, read by a saved row's answer too. The table
     asks none of it: which fields some record holds reads every row of the type to say none does.
     @param list<Column> $columns */
  public function glance(Collection $collection, array $columns): Glance
  {
    return new Glance($columns, $this->profile->byType($collection->type), $this->profile,
      $this->entities->held($collection->type, array_map(fn (Column $column): FieldDefinition => $column->field,
        $columns)));
  }

  /* Outside the read scope the columns are the anonymous document's, gate 3 included.
     @return list<Column> */
  public function columns(Account $account, Collection $collection): array
  {
    $outside = $this->authorizer->readsAsPublic($account, $collection->type);
    $writes = !$outside && $this->authorizer->writes($account, $collection);
    $uploads = $writes && $this->media->uploads($account);
    $type = $this->profile->byType($collection->type);
    $columns = [];
    foreach ($outside ? $this->filter->fields($collection) : $collection->fields as $held) {
      $field = $type->field($held->key);
      if ($field !== null) {
        $editable = $writes && $field->writtenBy->hasEditor();
        $columns[] = new Column($field, $field->label, $held->feed, $editable,
          (string) $this->schema->parameterName($field->key),
          $editable && $uploads && array_intersect($field->targets, $this->schema->emittedAs('MediaObject')) !== [],
          $this->schema->propertyUrl($field->key), $this->schema->objectUrl($field));
      }
    }

    return $columns;
  }
}
