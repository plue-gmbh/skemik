<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Session;
use Domain\Entity;
use Http\Csrf;
use Http\Response;
use Storage\CollectionStore;
use Storage\SettingStore;
use Support\Template;
use Support\Words;

/* The chrome around every admin screen (`view/partials/frame.php`), built in
   one place for every screen that has one, since no screen chooses it: the
   sidebar and the house's sites, the account menu, the probes' two lines and the
   house's first locale, around what the screen brings (`Admin\Screen`). The
   menu's name opens the account's person where the account reads it, since its
   name and picture are that record's. A screen
   opening on the sentence that a record is unknown answers 404 with it, since
   the address named nothing. */
final readonly class Frame
{
  public function __construct(
    private Sidebar $sidebar,
    private Probes $probes,
    private Session $session,
    private Rows $rows,
    private CollectionStore $collections,
    private SettingStore $settings,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
  ) {}

  public function around(Account $account, Entity $person, Screen $screen): Response
  {
    return Response::html($this->template->render('partials/frame', ['words' => $this->words,
      'account' => $account, 'title' => $screen->title,
      'menu' => new AccountMenu($account, $person, $this->rows, $this->session),
      'own' => $this->rows->opened($account, $person, $this->collections->all()),
      'notice' => $screen->notice, 'space' => $this->probes->space(), 'update' => $this->probes->update(),
      'sites' => $this->sidebar->sites(), 'csrf' => $this->csrf->token(),
      'entries' => $screen->entries ?? $this->sidebar->entries($account), 'open' => $screen->open,
      'locale' => $this->settings->locales()[0]->tag, 'main' => $screen->main,
      'islands' => $screen->islands, 'feed' => $screen->feed]), $screen->notice === '' ? 200 : 404);
  }
}
