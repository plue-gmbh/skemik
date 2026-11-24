<?php

declare(strict_types=1);

namespace Admin;

use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\ActionType;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Props;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Storage\ActivityLog;
use Storage\InstallItem;
use Storage\InstallObject;
use Storage\SettingStore;
use Support\Words;

/* The install's locales, `manageInstall` only, written from the Languages card
   (`view/partials/home-languages.php`). A setting and not a constant, since how
   many languages an organisation runs is its own business and nothing may count on two.
   The first is the default, so making one the default is moving it to the
   front and not a second operation. The default does not leave, so the last
   one never does, and another locale leaves only while nothing is written in
   it, neither a row nor a record's or an edge's words, or it would leave what
   no route reaches; `Storage\SettingStore` refuses that too. The
   card counts without a read scope, since only an owner holds `manageInstall`
   and an owner reads everything. A refusal is kept for the page the form
   returns to, as a field's addition is on its collection. */
final readonly class LocaleController
{
  /* The records a sample must find a language in to say its size: of fewer, six were said as twelve. */
  private const FEW = 100;

  public function __construct(
    private Authorizer $authorizer,
    private SettingStore $settings,
    private Session $session,
    private Csrf $csrf,
    private Words $words,
    private ActivityLog $log,
  ) {}

  /** A grown install counts on one record in N and says about. A sample cannot
      call a language empty or kept, nor size a small one, so one it finds few live records in has every
      record asked.
      @return list<LocaleRow> in order, the default first */
  public function rows(): array
  {
    $locales = $this->settings->locales();
    $every = $this->settings->countedEvery();
    $held = $this->settings->held($every);
    $few = fn (Locale $locale): bool => ($held[$locale->tag] ?? 0) < self::FEW * $every;
    if ($every > 1 && array_any($locales, $few)) {
      $every = 1;
      $held = $this->settings->held();
    }

    return array_map(fn (Locale $locale): LocaleRow => new LocaleRow($locale->tag,
      $this->counted($held[$locale->tag] ?? 0, isset($held[$locale->tag]), $every > 1),
      $this->refusal($locale, $locales, array_keys($held))), $locales);
  }

  public function add(Request $request): Response
  {
    $this->guard($request);
    $typed = trim((string) $request->form('tag'));
    try {
      $locale = new Locale($typed);
    } catch (MalformedValue) {
      return $this->refused($this->words->get('locale.unknown', ['tag' => $typed]));
    }
    $locales = $this->settings->locales();
    if (array_any($locales, fn (Locale $each): bool => $each->tag === $locale->tag)) {
      return $this->refused($this->words->get('locale.twice', ['tag' => $locale->tag]));
    }
    $this->settings->setLocales([...$locales, $locale]);
    $this->logged(ActionType::Create, $locale);

    return Response::redirect('/');
  }

  public function order(Request $request): Response
  {
    $this->guard($request);
    $locales = $this->settings->locales();
    $chosen = $this->held($locales, (string) $request->form('tag'));
    $this->settings->setLocales([$chosen, ...$this->without($locales, $chosen)]);
    if ($chosen->tag !== $locales[0]->tag) {
      $this->logged(ActionType::Update, $chosen, new Props(['default' => true]));
    }

    return Response::redirect('/');
  }

  public function remove(Request $request, string $tag): Response
  {
    $this->guard($request);
    $locales = $this->settings->locales();
    $leaving = $this->held($locales, $tag);
    $refusal = $this->refusal($leaving, $locales, array_keys($this->settings->held()));
    if ($refusal !== null) {
      return $this->refused($refusal);
    }
    $this->settings->setLocales($this->without($locales, $leaving));
    $this->logged(ActionType::Delete, $leaving);

    return Response::redirect('/');
  }

  private function logged(ActionType $type, Locale $locale, ?Props $said = null): void
  {
    $this->log->record($type, new InstallObject(InstallItem::Locale, $locale->tag), $said);
  }

  private function guard(Request $request): void
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::ManageInstall);
  }

  /** @param list<Locale> $locales
      @param list<string> $held the tags something is written in */
  private function refusal(Locale $locale, array $locales, array $held): ?string
  {
    return match (true) {
      $locale->tag === $locales[0]->tag => $this->words->get('locale.default_stays'),
      in_array($locale->tag, $held, true) => $this->words->get('locale.holds', ['tag' => $locale->tag]),
      default => null,
    };
  }

  /** @param list<Locale> $locales */
  private function held(array $locales, string $tag): Locale
  {
    return array_find($locales, fn (Locale $each): bool => $each->tag === $tag)
      ?? throw MalformedValue::named('locale of this install', $tag);
  }

  /** @param list<Locale> $locales
      @return list<Locale> */
  private function without(array $locales, Locale $leaving): array
  {
    return array_values(array_filter($locales, fn (Locale $each): bool => $each->tag !== $leaving->tag));
  }

  /* The count is of live records written in the locale or holding words in it,
     so a record in two counts in both and says so, beside the install's total. A
     locale the refusal still finds held through the trash or an edge's words is
     never called empty beside it. A rough count keeps two digits, which is what a sample knows. */
  private function counted(int $n, bool $held, bool $rough): string
  {
    $unit = 10 ** max(0, strlen((string) $n) - 2);
    $about = $this->words->number((int) round($n / $unit) * $unit);

    return match (true) {
      $n === 0 => $this->words->get($held ? 'locale.kept' : 'locale.empty'),
      $rough => $this->words->get('locale.about.records', ['n' => $about]),
      default => $this->words->count('locale', 'records', $n),
    };
  }

  private function refused(string $sentence): Response
  {
    $this->session->keep('locales', $sentence);

    return Response::redirect('/');
  }
}
