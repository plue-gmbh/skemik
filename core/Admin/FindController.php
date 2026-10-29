<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRepository;
use Auth\Capability;
use Domain\Entity;
use Domain\TranslationGroup;
use Http\Request;
use Http\Response;
use Profile\Profile;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Search;
use Support\Template;
use Support\Words;

/* `/find?q=`, the header's search: every collection the reader reads asked as its
   screen's `?q=` asks it and the pickers do (`Storage\Search`), each hit passing the
   reader's scope, a work once for all its languages. The collections stand in the
   order of their best hit, each with its first few and a link to the rest on its own
   screen, so the long list is the screen that already stands. For whoever reads the
   log, a person found who holds an account opens that colleague's changes, and an
   incident's six characters the error log at it. The question stays in the header's
   field, so the page holds results alone. */
final readonly class FindController
{
  private const SHOWN = 5;
  private const INCIDENT = '/^[a-hjkmnp-z2-9]{6}$/';

  public function __construct(
    private Search $search,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private AccountRepository $accounts,
    private ErrorLog $errors,
    private Rows $rows,
    private Sheet $sheet,
    private Profile $profile,
    private Frame $frame,
    private Template $template,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $asked = trim((string) $request->query('q'));
    $found = $asked === '' ? [] : $this->found($account, $asked);
    $main = $this->template->render('pages/find', ['words' => $this->words, 'asked' => $asked,
      'groups' => $this->grouped($found, $asked, count($found) >= Search::LIMIT),
      'lines' => $account->can(Capability::ReadLog) ? $this->logged($found, $asked) : [],
      'total' => count($found)]);

    return $this->frame->around($account, $person,
      new Screen($this->words->get('find.title'), $main, FeedPanel::at($request, '/feed')));
  }

  /* The hits the reader may open, a work's group once, by its best member.
     @return list<Entity> */
  private function found(Account $account, string $asked): array
  {
    [$found, $seen] = [[], []];
    $house = $this->collections->all();
    foreach ($this->search->search($asked, $this->rows->readings($account, $house)) as $id) {
      $entity = $this->entities->find($id);
      $root = TranslationGroup::rootOf($entity)->value;
      if (!isset($seen[$root]) && $this->rows->readable($account, $entity)) {
        [$seen[$root], $found[]] = [true, $entity];
      }
    }

    return $found;
  }

  /** @param list<Entity> $found
      @return list<FoundIn> by each collection's best hit */
  private function grouped(array $found, string $asked, bool $capped): array
  {
    $byType = [];
    foreach ($found as $entity) {
      $byType[$entity->type][] = $entity;
    }
    $groups = [];
    foreach ($byType as $type => $members) {
      $holder = array_find($this->collections->all(), fn (Collection $each): bool => $each->type === $type);
      if ($holder !== null) {
        $groups[] = new FoundIn($holder->name, '/c/' . $holder->slug->value . '?' . http_build_query(['q' => $asked]),
          array_map(fn (Entity $each): Found => $this->row($holder, $each), array_slice($members, 0, self::SHOWN)),
          max(0, count($members) - self::SHOWN), $capped);
      }
    }

    return $groups;
  }

  private function row(Collection $holder, Entity $entity): Found
  {
    return new Found(SidebarEntry::glyphOf($entity->type),
      $this->label->of($entity, $this->profile->byType($entity->type)), Rows::address($holder, $entity),
      $entity->slug->value, $this->sheet->state($entity));
  }

  /* What the logs hold of the question: the changes of each colleague found, and the incident typed.
     @param list<Entity> $found
     @return list<Found> */
  private function logged(array $found, string $asked): array
  {
    $lines = [];
    foreach ($found as $entity) {
      if ($entity->typeName() === 'Person' && $this->accounts->heldBy($entity->id) !== null) {
        $name = $this->label->of($entity, $this->profile->byType($entity->type));
        $lines[] = new Found('activity', $this->words->get('find.changes', ['name' => $name]),
          '/changes?' . http_build_query(['by' => $entity->id->value]));
      }
    }
    $incident = strtolower($asked);
    if (preg_match(self::INCIDENT, $incident) === 1 && $this->errors->entries(1, $incident) !== []) {
      $lines[] = new Found('alert', $this->words->get('find.incident', ['incident' => $incident]),
        '/settings/errors?' . http_build_query(['incident' => $incident]));
    }

    return $lines;
  }
}
