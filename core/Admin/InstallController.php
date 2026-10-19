<?php

declare(strict_types=1);

namespace Admin;

use Http\Endpoint;
use Http\Request;
use Http\Response;
use Http\RouteNotFound;
use Storage\SettingStore;
use Support\Template;
use Support\Words;

/* First run: answers while the house has no account and 404s forever
   after — no flag, no setting and no file to delete, so there is no step anyone
   can forget. The requirements come first, because an install that fails on the
   fourth screen has already created rows; the order after them is the folder
   and `config.php`, both proven, then the shape and the rows. A run that
   stopped after writing `config.php` resumes with the folder and house it
   names. No CSRF token: while this answers, anybody may post it directly, so a
   forged post can do nothing a direct one cannot. */
final readonly class InstallController
{
  /** @param \Closure(Premises): Founding $foundingAt */
  public function __construct(
    private bool $firstRun,
    private ?Premises $premises,
    private DataLayout $layout,
    private \Closure $foundingAt,
    private Template $template,
    private Words $words,
  ) {}

  /* The first run's two addresses, asked before `config.php` exists and again
     by a run resumed after it. */
  public function answer(Endpoint $endpoint, Request $request): Response
  {
    return match ($endpoint) {
      Endpoint::Install => $this->show($request),
      Endpoint::InstallRun => $this->run($request),
      default => throw RouteNotFound::for($request->method, $request->path),
    };
  }

  public function show(Request $request): Response
  {
    $this->requireFirstRun($request);

    return $this->page($request, null, 200);
  }

  public function run(Request $request): Response
  {
    $this->requireFirstRun($request);
    try {
      $form = InstallForm::from($request, $this->words);
      $premises = $this->premises ?? $this->settled($request);
      $founding = ($this->foundingAt)($premises);
      $founding->prepare();
      $cookie = $founding->handOver($founding->found($form),
        new LoginMail($this->template, $this->words, $form->name, $premises->address));

      return Response::redirect('/login')->with(['Set-Cookie' => $cookie]);
    } catch (InstallRefused $refused) {
      return $this->page($request, $refused->getMessage(), 422);
    }
  }

  /* The folder and `config.php`, both proven, before the database file exists. */
  private function settled(Request $request): Premises
  {
    $premises = new Premises($this->layout->settle($request->origin(),
      (string) $request->form('folder')), $request->origin());
    $this->layout->writeConfig($premises);

    return $premises;
  }

  private function requireFirstRun(Request $request): void
  {
    if (!$this->firstRun) {
      throw RouteNotFound::for($request->method, $request->path);
    }
  }

  private function page(Request $request, ?string $sentence, int $status): Response
  {
    $requirements = $this->layout->requirements($this->premises, $request->origin());
    $shipped = $this->words->shipped();

    return Response::html($this->template->render('pages/install', ['words' => $this->words,
      'requirements' => $requirements, 'sentence' => $sentence, 'asksFolder' => $this->premises === null,
      'costs' => array_filter($this->layout->costs(), fn (Requirement $cost): bool => !$cost->met),
      'ready' => array_filter($requirements, fn (Requirement $requirement): bool => !$requirement->met) === [],
      'form' => [...['timezone' => SettingStore::DEFAULT_TIMEZONE,
        'language' => explode('-', $this->words->language)[0]], ...$request->form],
      'timezones' => \DateTimeZone::listIdentifiers(),
      'languages' => array_combine($shipped, array_map(fn (string $code): string =>
        mb_ucfirst(\Locale::getDisplayLanguage($code, $code)), $shipped))]), $status);
  }
}
