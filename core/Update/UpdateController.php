<?php

declare(strict_types=1);

namespace Update;

use Admin\AccountMenu;
use Admin\FeedPanel;
use Admin\Probes;
use Admin\Rows;
use Admin\Sidebar;
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

/* `/settings/update`, the owner's, opened from the header's version on every
   page and from the Settings card's row: the running version and the key downloads are verified against, when
   the install last checked or that it could not, the last release installed by
   itself and whether it was a security release, or the release that cannot be
   installed and why, with the copy by hand named. No button: nothing here
   starts an update, which runs by itself (`Updater`). */
final readonly class UpdateController
{
  public function __construct(
    private Authorizer $authorizer,
    private UpdateCheck $check,
    private Signature $signature,
    private bool $enabled,
    private bool $early,
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
    $this->authorizer->requireHuman(Capability::RunUpdate);
    $report = $this->check->report();
    $main = $this->template->render('pages/update', ['words' => $this->words, 'report' => $report,
      'enabled' => $this->enabled, 'early' => $this->early, 'version' => Version::APP, 'key' => $this->keyId(),
      'stale' => $this->check->stale(), 'checked' => $this->when($report->checked),
      'installedAt' => $this->when($report->installedAt)]);

    return Response::html($this->template->render('partials/frame', ['words' => $this->words,
      'account' => $account, 'title' => $this->words->get('update.title'),
      'menu' => new AccountMenu($account, $person, $this->rows, $this->session),
      'sites' => $this->sidebar->sites(),
      'csrf' => $this->csrf->token(), 'entries' => $this->sidebar->entries($account), 'open' => 'update',
      'space' => $this->probes->space(), 'update' => $this->probes->update(),
      'locale' => $this->settings->locales()[0]->tag, 'main' => $main, 'islands' => false,
      'feed' => FeedPanel::at($request, '/feed')]));
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
    if ($stored === null) {
      return '';
    }
    $format = new \IntlDateFormatter($this->words->language, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT,
      $this->clock->zone->getName());

    return (string) $format->format(new \DateTimeImmutable($stored));
  }
}
