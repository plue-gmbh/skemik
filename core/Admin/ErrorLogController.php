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
use Storage\SettingStore;
use Support\Clock;
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
    private Sidebar $sidebar,
    private Probes $probes,
    private SettingStore $settings,
    private Session $session,
    private Csrf $csrf,
    private Rows $rows,
    private Clock $clock,
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

    return Response::html($this->template->render('partials/frame', ['words' => $this->words,
      'account' => $account, 'title' => $this->words->get('errors.title'),
      'menu' => new AccountMenu($account, $person, $this->rows, $this->session),
      'csrf' => $this->csrf->token(), 'entries' => $this->sidebar->entries($account), 'open' => null,
      'space' => $this->probes->space(), 'update' => $this->probes->update(),
      'locale' => $this->settings->locales()[0]->tag, 'main' => $main, 'islands' => false,
      'feed' => FeedPanel::at($request, '/feed')]));
  }

  private function when(?\DateTimeImmutable $at): string
  {
    $format = new \IntlDateFormatter($this->words->language, \IntlDateFormatter::MEDIUM,
      \IntlDateFormatter::MEDIUM, $this->clock->zone->getName());

    return $at === null ? '—' : (string) $format->format($at);
  }
}
