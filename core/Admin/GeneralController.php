<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\RelayFailed;
use Auth\Session;
use Auth\Smtp;
use Domain\Entity;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Http\Upload;
use Storage\SettingStore;
use Support\Template;
use Support\Words;

/* `/settings/general`, `manageHouse`, opened from the Settings card: one form
   over the two stores, saying which is which, since a restore elsewhere brings
   the one and not the other. The address is shown and never edited: a typo would
   send every colleague's login mail to another domain, and moving the domain is
   the operator's. A changed relay is proven before anything is kept, and a
   refusal shows the form again as typed, the password aside. Beside them what
   the host sets and nothing here changes: the largest upload, and what slows
   every answer (`Probes::costs`). */
final readonly class GeneralController
{
  public function __construct(
    private Authorizer $authorizer,
    private SettingStore $settings,
    private ConfigFile $config,
    private Premises $premises,
    private Probes $probes,
    private Frame $frame,
    private Session $session,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::ManageHouse);

    return $this->screen($account, $person, $request, [], null);
  }

  /* `config.php` first, since it is the store a host may refuse, so a refusal leaves the database as it was. */
  public function save(Account $account, Entity $person, Request $request): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::ManageHouse);
    try {
      $form = GeneralForm::from($request, $this->config->writable() ? $this->config->read() : null, $this->words);
      $form->proving && $this->relay($form)->probe();
      $form->config === null || $this->config->change($form->config);
    } catch (SettingRefused $refused) {
      return $this->refused($account, $person, $request, $refused->getMessage());
    } catch (RelayFailed $failed) {
      return $this->refused($account, $person, $request,
        $this->words->get('general.refused.relay', ['reason' => $this->words->get('relay.' . $failed->fault->value,
          ['detail' => $failed->detail])]));
    } catch (ConfigUnwritten) {
      return $this->refused($account, $person, $request, $this->words->get('general.refused.unwritten'));
    }
    $this->settings->setTimezone($form->timezone);
    $this->settings->setChromeLocale($form->language);
    $this->session->keep('general', $this->words->get('general.saved'));

    return Response::redirect('/settings/general');
  }

  private function relay(GeneralForm $form): Smtp
  {
    /** @var array{host: string, port: int, user: string, password: string, sender: string} $relay */
    $relay = $form->config['relay'] ?? [];

    return new Smtp($relay['host'], $relay['port'], $relay['user'], $relay['password'], $relay['sender'],
      (string) parse_url($this->premises->address, PHP_URL_HOST));
  }

  private function refused(Account $account, Entity $person, Request $request, string $sentence): Response
  {
    $page = $this->screen($account, $person, $request, array_map(strval(...), $request->form), $sentence);

    return new Response(422, $page->body, $page->headers);
  }

  /** @param array<string, string> $typed what a refused form carried, shown in place of what is held */
  private function screen(Account $account, Entity $person, Request $request, array $typed, ?string $refusal)
    : Response
  {
    [$shipped, $config] = [$this->words->shipped(), $this->config->read()];
    $held = $this->held($config);
    $main = $this->template->render('pages/general', ['words' => $this->words,
      'values' => [...$held, ...array_intersect_key($typed, $held)],
      'kept' => GeneralForm::heldRelay($config)['password'] !== '', 'address' => $this->premises->address,
      'writable' => $this->config->writable(), 'timezones' => \DateTimeZone::listIdentifiers(),
      'upload' => Intake::size(Upload::limit()), 'costs' => $this->probes->costs(),
      'languages' => array_combine($shipped, array_map(fn (string $code): string =>
        mb_ucfirst(\Locale::getDisplayLanguage($code, $code)), $shipped)),
      'notice' => $refusal === null ? $this->session->take('general') : null, 'refusal' => $refusal,
      'csrf' => $this->csrf->token()]);

    return $this->frame->around($account, $person,
      new Screen($this->words->get('general.title'), $main, FeedPanel::at($request, '/feed')));
  }

  /** @param array<string, mixed> $config
      @return array<string, string> each field as it stands, the password never among them */
  private function held(array $config): array
  {
    $relay = GeneralForm::heldRelay($config);

    return ['timezone' => $this->settings->timezone()->getName(),
      'language' => $this->settings->chromeLocale()?->tag ?? '', 'host' => $relay['host'],
      'port' => (string) $relay['port'], 'user' => $relay['user'], 'sender' => $relay['sender'],
      'contact' => (string) ($config['security_contact'] ?? ''), 'note' => (string) ($config['login_note'] ?? ''),
      'link' => (string) ($config['login_link'] ?? '')];
  }
}
