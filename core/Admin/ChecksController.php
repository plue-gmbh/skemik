<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\Entity;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\UnknownType;
use Domain\Violation;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Profile\Profile;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\JsonFailed;
use Support\Template;
use Support\Words;

/* `/settings/checks`, `manageHouse`: the holdings report as `Update\Checkup` left
   it, opened from Needs a look's line, so a house whose records stopped fitting
   learns which. A required field missing is listed too, since its own line opens
   one record at a time. No button runs the check now, a mended record leaving at
   the next slice, and none repairs, since the screen cannot know what the house
   meant. Capped like the error log: a release that gives a type a † can leave
   thousands, and each line reads its record. */
final readonly class ChecksController
{
  private const SHOWN = 200;

  public function __construct(
    private Authorizer $authorizer,
    private SettingStore $settings,
    private EntityRepository $entities,
    private CollectionStore $collections,
    private Profile $named,
    private Sidebar $sidebar,
    private Probes $probes,
    private Session $session,
    private Csrf $csrf,
    private Rows $rows,
    private Clock $clock,
    private Template $template,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::ManageHouse);
    $report = $this->settings->holdingsReport();
    $found = $this->ordered($report?->violations ?? []);
    $collections = $this->collections->all();
    $entries = array_map(fn (Violation $violation): CheckEntry => $this->entry($violation, $collections),
      array_slice($found, 0, self::SHOWN));
    $main = $this->template->render('pages/checks', ['words' => $this->words, 'report' => $report,
      'at' => $report === null ? '' : $this->when($report->at), 'count' => count($found),
      'shown' => self::SHOWN, 'entries' => $entries]);

    return Response::html($this->template->render('partials/frame', ['words' => $this->words,
      'account' => $account, 'title' => $this->words->get('checks.title'),
      'menu' => new AccountMenu($account, $person, $this->rows, $this->session),
      'sites' => $this->sidebar->sites(),
      'csrf' => $this->csrf->token(), 'entries' => $this->sidebar->entries($account), 'open' => null,
      'space' => $this->probes->space(), 'update' => $this->probes->update(),
      'locale' => $this->settings->locales()[0]->tag, 'main' => $main, 'islands' => false,
      'feed' => FeedPanel::at($request, '/feed')]));
  }

  /* The house's own first, then what Needs a look counts as the check's, then
     the required fields that have their own line.
     @param list<Violation> $violations
     @return list<Violation> */
  private function ordered(array $violations): array
  {
    $rank = fn (Violation $violation): int => match (true) {
      $violation->record === null => 0,
      $violation->reason !== Violation::REQUIRED => 1,
      default => 2,
    };
    usort($violations, fn (Violation $one, Violation $other): int => $rank($one) <=> $rank($other));

    return $violations;
  }

  /* A row the check could not read as a record, or one purged since, is named and opens nothing.
     @param list<Collection> $collections */
  private function entry(Violation $violation, array $collections): CheckEntry
  {
    $reason = $violation->reason === Violation::REQUIRED
      ? $this->words->get('checks.required') : $violation->reason;
    if ($violation->record === null) {
      return new CheckEntry(null, 'alert', $violation->field, '', $reason);
    }
    try {
      $entity = $this->entities->find($violation->record);
      $type = $this->named->byType($entity->type);
    } catch (EntityNotFound|MalformedValue|JsonFailed|UnknownType) {
      return new CheckEntry(null, 'alert', $this->words->get('checks.gone'), '',
        $violation->field . ': ' . $reason);
    }
    $held = array_find($collections, fn (Collection $each): bool => $each->type === $entity->type);

    return new CheckEntry($this->opens($entity, $held), SidebarEntry::glyphOf($entity->type),
      $this->label->of($entity, $type), $held->name ?? '',
      ($type->field($violation->field)?->label ?? $violation->field) . ': ' . $reason);
  }

  private function opens(Entity $entity, ?Collection $held): ?string
  {
    return match (true) {
      $held === null => null,
      $entity->deletedAt !== null => '/trash/' . $held->slug->value,
      default => Rows::address($held, $entity),
    };
  }

  private function when(\DateTimeImmutable $at): string
  {
    $format = new \IntlDateFormatter($this->words->language, \IntlDateFormatter::MEDIUM,
      \IntlDateFormatter::SHORT, $this->clock->zone->getName());

    return (string) $format->format($at);
  }
}
