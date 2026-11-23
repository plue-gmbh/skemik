<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\NotPermitted;
use Auth\Session;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Slug;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\FieldDefinition;
use Profile\Profile;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Figure;
use Storage\Figures;
use Storage\Query;
use Storage\Question;
use Storage\Range;
use Storage\SettingStore;
use Support\Uuid;
use Support\Words;

/* The home screen's saved questions (`view/partials/home-questions.php`), saved and deleted on a collection's
   screen by whoever arranges it. A line holds no name: its title is the collection, the narrowing and the figure
   in the reader's words. It is asked through `QueryBar` as the collection's screen is, so its figure is the one
   that screen counts for this reader. A reader the query refuses, or who does not read a record it names, is
   shown no line, since a slug is a name; a record that no longer stands is said as its address spells it,
   so its question is seen and deleted. A narrowing is saved once, a second save taking the first one's place,
   since its screen offers one mark to delete it. A question whose field has left the collection stays for
   whoever arranges it, without a figure. No write is logged: the log's newest line dates the feed, and a
   question changes nothing a frontend reads. */
final readonly class QuestionController
{
  public function __construct(
    private SettingStore $settings,
    private CollectionStore $collections,
    private Authorizer $authorizer,
    private QueryBar $bar,
    private EntityRepository $entities,
    private Figures $figures,
    private Rows $rows,
    private Profile $profile,
    private Session $session,
    private Csrf $csrf,
    private Uuid $uuid,
    private Words $words,
  ) {}

  /** @return list<QuestionLine> in the order saved, each the reader has a reading of */
  public function lines(Account $account): array
  {
    return array_values(array_filter(array_map(fn (Question $question): ?QuestionLine
      => $this->line($account, $question), $this->settings->questions())));
  }

  /* Null where the reader is shown none; the title first, since a record it names unread asks no figure. */
  private function line(Account $account, Question $question): ?QuestionLine
  {
    $collection = $this->collections->bySlug(new Slug($question->collection));
    $title = $collection === null ? null : $this->title($account, $question, $collection);
    if ($collection === null || $title === null) {
      return null;
    }
    try {
      $figure = $this->asked($account, $question, $collection);
    } catch (MalformedValue) {
      $figure = $this->authorizer->arranges($account, $collection) ? '—' : null;
    }

    return $figure === null ? null : new QuestionLine($question->id, $title, $figure, self::address($question),
      SidebarEntry::glyphOf($collection->type), $collection->name);
  }

  public function save(Request $request): Response
  {
    $this->csrf->require($request);
    $account = $this->authorizer->requireHuman(Capability::ManageFields);
    $collection = $this->arranged($account, (string) $request->form('collection'));
    $asked = explode(':', (string) $request->form('figure'), 2) + [1 => null];
    $figure = Figure::tryFrom($asked[0]) ?? throw MalformedValue::named('figure', $asked[0]);
    parse_str((string) $request->form('query'), $sent);
    $query = Question::narrowing(array_filter($sent, is_string(...)));
    $question = new Question($this->uuid->generate(), $collection->slug->value, $query, $figure,
      $figure === Figure::Count ? null : $asked[1]);
    $this->asked($account, $question, $collection);
    $others = array_filter($this->settings->questions(), fn (Question $each): bool
      => $each->collection !== $question->collection || $each->query !== $question->query);
    $this->settings->setQuestions([...array_values($others), $question]);
    $this->session->keep('fielded', $this->words->get('question.saved'));

    return Response::redirect(self::address($question));
  }

  public function remove(Request $request, string $id): Response
  {
    $this->csrf->require($request);
    $account = $this->authorizer->requireHuman(Capability::ManageFields);
    $questions = $this->settings->questions();
    $leaving = array_find($questions, fn (Question $each): bool => $each->id === $id)
      ?? throw EntityNotFound::withId($id);
    $this->arranged($account, $leaving->collection);
    $this->settings->setQuestions(array_values(array_filter($questions,
      fn (Question $each): bool => $each !== $leaving)));
    $this->session->keep('fielded', $this->words->get('question.removed'));

    return Response::redirect(self::address($leaving));
  }

  /* The collection, what narrows it and, for any figure but the count, the figure, in the reader's words;
     null where the narrowing names a record this reader does not read (`QueryBar::titled`). */
  private function title(Account $account, Question $question, Collection $collection): ?string
  {
    parse_str($question->query, $parameters);
    $narrowing = $this->bar->titled($account, new Request('GET', '', array_filter($parameters, is_string(...))),
      $collection);
    $field = $this->profile->byType($collection->type)->field((string) $question->field);
    $figure = $question->figure === Figure::Count ? null : $this->words->get('question.'
      . $question->figure->value, ['field' => $field?->label ?? (string) $question->field]);

    return $narrowing === null ? null : implode(' · ', array_filter([$collection->name, ...$narrowing, $figure]));
  }

  /* Null where the reader has no reading of what is asked; a query the address would refuse is refused here. */
  private function asked(Account $account, Question $question, Collection $collection): ?string
  {
    parse_str($question->query, $parameters);
    $query = $this->bar->query($account, new Request('GET', '/c/' . $question->collection,
      array_filter($parameters, is_string(...))), $collection);
    if ($query === null) {
      return null;
    }
    if ($question->figure === Figure::Count) {
      return $this->words->number($this->entities->count($query));
    }
    $number = $this->read($account, $collection, fn (FieldDefinition $field): bool
      => Figures::reads($field) && $field->key === $question->field);
    $figures = $question->figure === Figure::Sum ? $this->figures->sum($query, $number) ?: ['' => 0]
      : $this->latest($account, $collection, $query, $number);

    return $figures === [] ? '—' : implode(', ', array_map($this->said(...), $figures, array_keys($figures)));
  }

  /* A total in its currency's own digits, *1.240,00 €*, since a sum of prices carries the float's rounding. */
  private function said(int|float $value, string $currency): string
  {
    if ($currency === '') {
      return (string) (new \NumberFormatter($this->words->language, \NumberFormatter::DECIMAL))->format($value);
    }

    return (string) (new \NumberFormatter($this->words->language, \NumberFormatter::CURRENCY))
      ->formatCurrency((float) $value, $currency);
  }

  /** By the collection's first date as this reader reads it, the one its timeline files by.
      @return array<string, int|float> */
  private function latest(Account $account, Collection $collection, Query $query, FieldDefinition $number): array
  {
    $dated = fn (FieldDefinition $field): bool => in_array($field->kind, Range::DATES, true);

    return $this->figures->latest($query, $this->read($account, $collection, $dated), $number);
  }

  /** The first field of the collection this reader reads that passes; none is refused as a field the type
      lacks is.
      @param \Closure(FieldDefinition): bool $passes */
  private function read(Account $account, Collection $collection, \Closure $passes): FieldDefinition
  {
    $type = $this->profile->byType($collection->type);
    foreach ($this->rows->fields($account, $collection) as $held) {
      $field = $type->field($held->key);
      if ($field !== null && $passes($field)) {
        return $field;
      }
    }

    throw MalformedValue::named('question of ' . $type->name, 'no field this reader reads answers it');
  }

  private function arranged(Account $account, string $slug): Collection
  {
    try {
      $collection = $this->collections->bySlug(new Slug($slug));
    } catch (MalformedValue) {
      $collection = null;
    }
    if ($collection === null || $this->authorizer->readsAsPublic($account, $collection->type)) {
      throw EntityNotFound::withId($slug);
    }

    return $this->authorizer->arranges($account, $collection) ? $collection
      : throw NotPermitted::outsideWriteScope(basename($collection->type));
  }

  /** @psalm-taint-escape header a slug from the release's list and a query `Question::narrowing` wrote, whose
      `http_build_query` encodes every line break */
  private static function address(Question $question): string
  {
    return '/c/' . $question->collection . ($question->query === '' ? '' : '?' . $question->query);
  }
}
