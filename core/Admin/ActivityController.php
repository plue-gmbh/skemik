<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\Entity;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Storage\Activity;
use Storage\ActivityLog;
use Storage\CollectionStore;
use Support\Template;
use Support\Words;

/* `/changes`, `readLog`: the whole log, whose contract is the header of
   `view/pages/activity.php`, a page at a time down the time index, or one
   actor's. Not under `/settings/`, since it is Last changed's way further back
   and no setting of the house. Every row is a line, so its pages count what the log holds; the
   lines are `Admin\Changes`'s, as Last changed draws them. A former value is
   redacted from its line, `eraseData`, which `Storage\ActivityLog` logs. */
final readonly class ActivityController
{
  private const PER_PAGE = 50;

  public function __construct(
    private Authorizer $authorizer,
    private ActivityLog $log,
    private Changes $changes,
    private CollectionStore $collections,
    private Frame $frame,
    private Session $session,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::ReadLog);
    $by = $request->query('by') ?: null;
    $page = min(max(1, (int) $request->query('page')), intdiv(PHP_INT_MAX, self::PER_PAGE));
    $main = $this->template->render('pages/activity', ['words' => $this->words,
      'lines' => $this->lines($account, $by, $page), 'total' => $this->log->count($by), 'page' => $page,
      'perPage' => self::PER_PAGE, 'by' => $by, 'redacts' => $account->can(Capability::EraseData),
      'notice' => $this->session->take('activity'), 'csrf' => $this->csrf->token()]);

    return $this->frame->around($account, $person,
      new Screen($this->words->get('change.all'), $main, FeedPanel::at($request, '/feed')));
  }

  /* Back to the page the line stood on, which now says it was redacted. */
  public function redact(Request $request, string $id): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::EraseData);
    $this->log->redactResult($id);
    $this->session->keep('activity', $this->words->get('change.redacted'));
    $back = array_filter(['by' => $request->form('by'), 'page' => $request->form('page')]);

    return Response::redirect('/changes' . ($back === [] ? '' : '?' . http_build_query($back)));
  }

  /** @return list<Change> */
  private function lines(Account $account, ?string $by, int $page): array
  {
    $collections = $this->collections->all();

    $rows = $this->log->page($by, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

    return array_map(fn (Activity $activity): Change
      => $this->changes->line($account, $activity, $collections) ?? $this->changes->gone($activity), $rows);
  }
}
