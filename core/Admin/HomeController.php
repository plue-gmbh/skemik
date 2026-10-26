<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Capability;
use Auth\Session;
use Auth\Smtp;
use Domain\Entity;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Storage\SettingStore;
use Support\Template;
use Support\Words;
use Update\Backup;
use Update\FileCheck;

/* The overview, decided rather than accumulated: the title, one line of totals,
   six cards and the feed panel. The totals are counted past `Feed\PublicFilter`
   under the read scope, or a scoped colleague learns from a sum what the records
   would not have told them, and a number stands only where somebody is expected to
   move it. The cards' order is the argument, what has to be done first: Needs a
   look, Grants, Languages, Last changed, Accounts, Settings, so each column holds
   one question. A card is drawn only for an account holding its capability, and
   Accounts for every account in a shape that follows it; none is hidden for being
   empty, since a card that comes and goes moves every card after it. Each card's
   contract is its partial's header (`home-*.php`). */
final readonly class HomeController
{
  public function __construct(
    private Frame $frame,
    private Sidebar $sidebar,
    private SettingStore $settings,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
    private Moments $moments,
    private Session $session,
    private NeedsLook $needsLook,
    private Changes $changes,
    private Colleagues $colleagues,
    private Register $register,
    private LocaleController $locales,
    private Backup $backup,
    private FileCheck $files,
    private ErrorLog $errors,
    private ?Smtp $relay,
    private Premises $premises,
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $entries = $this->sidebar->entries($account);
    $records = array_sum(array_map(fn (SidebarEntry $entry): int => $entry->count, $entries));
    $main = $this->template->render('pages/home', ['words' => $this->words,
      'address' => explode('://', $request->origin(), 2)[1],
      'collections' => count(array_filter($entries, fn (SidebarEntry $entry): bool => $entry->count > 0)),
      'records' => $records, 'cards' => $this->cards($account)]);

    return $this->frame->around($account, $person, new Screen($this->words->get('home.title'), $main,
      FeedPanel::at($request, '/feed/' . $this->settings->locales()[0]->tag), entries: $entries));
  }

  /** @return array<string, array<string, list<Finding>|list<Change>|list<Colleague>|list<RegisterEntry>
      |list<LocaleRow>|list<string>|array<string, string>|string|bool|Health|null>> each card drawn for the
      account, in order, with the values its partial reads */
  private function cards(Account $account): array
  {
    $house = $account->can(Capability::ManageHouse);
    $manage = $account->can(Capability::ManageAccounts);
    $cards = ['needs_look' => ['findings' => $this->needsLook->findings($account)],
      'grants' => $account->can(Capability::ManageTokens) ? ['entries' => $this->register->entries($account),
        'collections' => $this->register->collections(), 'until' => $this->register->until(),
        'csrf' => $this->csrf->token()] : null,
      'languages' => $house ? ['rows' => $this->locales->rows(), 'csrf' => $this->csrf->token(),
        'refused' => (string) $this->session->take('locales')] : null,
      'changes' => ['changes' => $this->changes->latest($account),
        'log' => $account->can(Capability::ReadLog)],
      'accounts' => ['colleagues' => $this->colleagues->all($account), 'manage' => $manage,
        ...$manage ? ['types' => $this->colleagues->types(), 'unreadable' => $this->colleagues->unreadable(),
        'csrf' => $this->csrf->token()] : []],
      'settings' => $house ? $this->settingsCard($account) : null];

    return array_filter($cards, fn (?array $card): bool => $card !== null);
  }

  /** @return array<string, string|Health|null> the Settings card's rows, each status row with its health */
  private function settingsCard(Account $account): array
  {
    $errors = $account->can(Capability::ReadLog);

    return ['timezone' => $this->settings->timezone()->getName(), 'language' => $this->adminLanguage(),
      'address' => $this->premises->address, 'mail' => $this->mail(),
      'contact' => $this->premises->securityContact, 'note' => $this->premises->loginNote,
      'link' => $this->premises->loginLink,
      'backups' => $this->backups(), 'backupsHealth' => $this->backupsHealth(),
      'files' => $this->shipped(), 'filesHealth' => $this->shippedHealth(),
      'errors' => $errors ? $this->failing() : null, 'errorsHealth' => $errors ? $this->errorsHealth() : null];
  }

  /* The Settings card's admin language row, named in the reader's own: `/install` always sets one. */
  private function adminLanguage(): string
  {
    $chosen = $this->settings->chromeLocale();

    return $chosen === null ? '—' : mb_ucfirst(\Locale::getDisplayLanguage($chosen->tag, $this->words->language));
  }

  /* The Settings card's mail half: the sender of the relay `config.php` names, never its login, else the host's
     `mail()`. */
  private function mail(): string
  {
    return $this->relay === null ? $this->words->get('home.mail_host') : $this->relay->sender;
  }

  /* Failing once the daily run that should have replaced the newest snapshot did not, so a quiet weekend is no
     failure; none yet, or none by `config.php`'s choice, is no state to judge. */
  private function backupsHealth(): ?Health
  {
    if ($this->backup->list() === []) {
      return null;
    }

    return $this->backup->report()?->failed === null ? Health::Ok : Health::Failing;
  }

  /* The Settings card's files row as the daily run last compared them, none before its first run: each kind
     of difference with its paths, so a release half copied names what is missing. */
  private function shipped(): ?string
  {
    $report = $this->files->report();
    if ($report === null || !$report->listed || $report->differing() === []) {
      return $report === null ? null : $this->words->get($report->listed ? 'home.files_match' : 'home.files_unlisted',
        ['version' => $report->version]);
    }
    $kinds = array_filter(['changed' => $report->changed, 'missing' => $report->missing,
      'added' => $report->added]);

    return implode(' · ', array_map(fn (string $kind, array $paths): string => $this->words->get('home.files_'
      . $kind, ['paths' => implode(', ', $paths)]), array_keys($kinds), $kinds));
  }

  /* No list is no state to judge, as a `-dev` install carries none. */
  private function shippedHealth(): ?Health
  {
    $report = $this->files->report();
    if ($report === null || !$report->listed) {
      return null;
    }

    return $report->differing() === [] ? Health::Ok : Health::Failing;
  }

  /* A failure the owner has read is no longer news, but no reason for green either. */
  private function errorsHealth(): ?Health
  {
    return match (true) {
      $this->errors->unseen() => Health::Failing,
      $this->errors->entries(1) === [] => Health::Ok,
      default => null,
    };
  }

  /* The Settings card's backups row: when the last snapshot was taken. */
  private function backups(): string
  {
    $latest = $this->backup->list()[0] ?? null;
    if ($latest === null) {
      return $this->words->get($this->backup->daily ? 'home.backups_none' : 'home.backups_off');
    }

    return $this->words->get('home.backups_last', ['when' => $this->moments->time($latest->at)]);
  }

  /* The Settings card's error log row: when the last failure was logged, and its incident. */
  private function failing(): string
  {
    $latest = $this->errors->entries(1)[0] ?? null;

    return $latest === null ? $this->words->get('home.errors_none')
      : $this->words->get('home.errors_last', ['incident' => $latest->incident,
        'when' => $latest->time === null ? '—' : $this->moments->time($latest->time)]);
  }
}
