<?php

declare(strict_types=1);

namespace Update;

use Admin\ConfigFile;
use Admin\FeedPanel;
use Admin\Frame;
use Admin\Moments;
use Admin\Screen;
use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Domain\Entity;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Support\Template;
use Support\Words;

/* `/settings/update`, the owner's, opened from the header's version on every
   page and from the Settings card's row: the running version and the key downloads are verified against, when
   the install last checked or that it could not, the last release installed by
   itself and whether it was a security release, or the release that cannot be
   installed and why, with the copy by hand named. One button, a check now, for
   an owner who knows a release is out and will not wait the hour, waiting while a
   snapshot or an update holds the lock; what it finds is installed as the hourly
   check's is (`Updater`). Beside it the choice `config.php` keeps, off, on or
   early, disabled where PHP may not write that file. */
final readonly class UpdateController
{
  public function __construct(
    private Authorizer $authorizer,
    private UpdateCheck $check,
    private Updater $updater,
    private Signature $signature,
    private bool $enabled,
    private bool $early,
    private ConfigFile $config,
    private Frame $frame,
    private Csrf $csrf,
    private Moments $moments,
    private Template $template,
    private Words $words,
  ) {}

  /* While an install holds the lock, the press's own as a rule, since it answers before installing, the
     screen is the waiting page: it opens the screen again until that ends, the move between. */
  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::RunUpdate);
    $report = $this->check->report();
    $busy = $this->updater->busy();
    if ($this->enabled && $this->updater->installing()) {
      return $this->waiting();
    }
    $main = $this->template->render('pages/update', ['words' => $this->words, 'report' => $report,
      'enabled' => $this->enabled, 'early' => $this->early, 'version' => Version::APP, 'key' => $this->keyId(),
      'stale' => $this->check->stale(), 'checked' => $this->when($report->checked),
      'installedAt' => $this->when($report->installedAt), 'csrf' => $this->csrf->token(),
      'busy' => $busy, 'switchable' => $this->config->writable()]);

    return $this->frame->around($account, $person,
      new Screen($this->words->get('update.title'), $main, FeedPanel::at($request, '/feed'), 'update'));
  }

  public function check(Request $request): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::RunUpdate);

    return $this->updater->asked(Response::redirect('/settings/update'), $this->waiting());
  }

  /* A choice that is none of the three changes nothing, so a stray value never declines every release. */
  public function mode(Request $request): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::RunUpdate);
    $chosen = ['off' => false, 'on' => true, 'early' => 'early'][(string) $request->form('updates')] ?? null;
    $chosen === null || $this->config->change(['updates' => $chosen]);

    return Response::redirect('/settings/update');
  }

  /* Opening the screen again once the wait is over, the press's answer standing at the address it was sent to. */
  private function waiting(): Response
  {
    return Response::html($this->template->render('pages/waiting', ['words' => $this->words,
      'sentence' => $this->words->get('boot.updating'), 'reopen' => '/settings/update']));
  }

  /* Empty while the source holds no key, which the screen says in place of an id. */
  private function keyId(): string
  {
    try {
      return $this->signature->keyId();
    } catch (UpdateRefused) {
      return '';
    }
  }

  private function when(?string $stored): string
  {
    return $stored === null ? '' : $this->moments->time($stored);
  }
}
