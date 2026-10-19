<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Domain\Entity;
use Http\Request;
use Http\Response;
use Support\Template;
use Support\Words;

/* `/settings/errors`, `readLog`: the error log, never called the log, which is
   the activity log's name (`Admin\ActivityController`). Its search by incident
   id is the reason it exists: "there's an error" plus six characters is the
   support call this product can afford, and the operator who cannot open a
   shell is the one it is for. Read-only, no clearing, no download, no rotation
   button, since the file is not the holdings and nothing in it is the
   operator's to curate; opening it only marks what stands as seen (`ErrorLog`).
   An `Admin` screen although `Http\Logger` writes the file: the writer is on
   the request path, and a reader of 200 lines is not. */
final readonly class ErrorLogController
{
  private const SHOWN = 200;

  public function __construct(
    private Authorizer $authorizer,
    private ErrorLog $log,
    private Frame $frame,
    private Moments $moments,
    private Template $template,
    private Words $words,
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::ReadLog);
    $incident = strtolower(trim($request->query('incident') ?? ''));
    $this->log->see();
    $main = $this->template->render('pages/errors', ['words' => $this->words, 'incident' => $incident,
      'entries' => $this->log->entries(self::SHOWN, $incident), 'shown' => self::SHOWN, 'when' => $this->when(...)]);

    return $this->frame->around($account, $person,
      new Screen($this->words->get('errors.title'), $main, FeedPanel::at($request, '/feed')));
  }

  /* To the second, since two lines of one burst are told apart by it. */
  private function when(?\DateTimeImmutable $at): string
  {
    return $at === null ? '—' : $this->moments->time($at, \IntlDateFormatter::MEDIUM);
  }
}
