<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRepository;
use Auth\CodeDelivery;
use Auth\LoginCode;
use Auth\Session;
use Http\Csrf;
use Http\CsrfRefused;
use Http\Request;
use Http\Response;
use Storage\EntityRepository;
use Storage\RateLimit;
use Support\Template;
use Support\Words;

/* The two steps of a login and the way out. An ask answers one sentence whoever
   asks and issues its code once the answer has left, so no address is told apart
   by what is said or, where PHP can end an answer first (`DataLayout::costs`), by
   time. `Storage\RateLimit` is asked first, per address and client at half what
   one mailbox is sent, so a colleague's mail is withheld only while several
   clients ask, and past the mailbox's own the floor is still written for the
   operator. Only an ask within them begins a session, so `/login` writes no file
   per visitor and its form carries no CSRF token. The ask's `Origin` stands in:
   posted from another site it arrives without the `SameSite=Lax` cookie, and the
   session it began would replace that browser's login. A script sends no
   `Origin` and passes. The mail speaks the asking browser's language, the
   account's being known only once it is in. The house's own line under the
   form is `config.php`'s and never the request's, so the page stays the same
   whoever asks. */
final readonly class LoginController
{
  public function __construct(
    private Session $session,
    private AccountRepository $accounts,
    private EntityRepository $people,
    private LoginCode $codes,
    private CodeDelivery $delivery,
    private RateLimit $asking,
    private RateLimit $entering,
    private RateLimit $perClient,
    private RateLimit $perMailbox,
    private Csrf $csrf,
    private Template $template,
    private Words $words,
    private Premises $premises,
  ) {}

  /* Straight to the code step for the owner a first run just made. A colleague
     sent here from a page returns to it once logged in, the path riding the form
     to the ask that keeps it. */
  public function form(bool $loggedIn, Request $request): Response
  {
    $to = self::path((string) $request->query('to'));
    if ($loggedIn) {
      return Response::redirect($to ?? '/');
    }

    return $this->session->take('handed') === null ? $this->address($to)
      : $this->code($this->words->get('login.handed'));
  }

  public function ask(Request $request): Response
  {
    self::fromHere($request);
    $address = Account::address((string) $request->form('email'));
    $to = self::path((string) $request->form('to'));
    if (!$this->allowed($this->asking, $request, 'address:' . $address . ' ' . $request->client)) {
      return $this->address($to, $this->words->get('login.too_many'), 429);
    }
    $this->session->begin();
    $to !== null && $this->session->remember('return', $to);
    $account = filter_var($address, FILTER_VALIDATE_EMAIL) === false ? null : $this->accounts->byEmail($address);
    $mails = $this->perMailbox->hit('login:mailbox:' . hash('sha256', $address));
    $key = $this->session->key();
    /* Work follows either answer, empty where nothing is sent, or its headers would tell the two apart. */
    $work = $account === null || $account->disabledAt !== null ? static function (): void {}
      : fn () => $this->deliver($account, $key, $mails);

    return $this->code($this->words->get('login.sent'))->after($work);
  }

  private function deliver(Account $account, string $key, bool $mails): void
  {
    $name = (string) $this->people->find($account->person)->props->string('name');
    $mail = (new LoginMail($this->template, $this->words, $name, $this->premises->address))
      ->with($this->codes->issue($account, $key));
    $mails ? $this->delivery->send($account, $mail) : $this->delivery->floor($account, $mail);
  }

  public function redeem(Request $request): Response
  {
    $this->csrf->require($request);
    if (!$this->allowed($this->entering, $request, 'code:' . $this->session->key())) {
      return $this->code($this->words->get('login.too_many'), 429);
    }
    $account = $this->codes->redeem((string) $request->form('code'), $this->session->key());
    if ($account === null) {
      return $this->code($this->words->get('login.refused'), 422);
    }
    $to = $this->session->remembered('return') ?? '/';
    $this->session->logIn($account->id);
    $this->accounts->recordLogin($account);

    return Response::redirect($to);
  }

  public function logOut(Request $request, ?Account $account): Response
  {
    $this->csrf->require($request);
    if ($account !== null && $request->form('everywhere') !== null) {
      $this->session->endOthers($account->id);
    }
    $this->session->logOut();

    return Response::redirect('/login');
  }

  /* Both buckets count on every attempt, so a refused one does not spare the other. */
  private function allowed(RateLimit $limit, Request $request, string $what): bool
  {
    $address = $limit->hit('login:' . hash('sha256', $what));
    $client = $this->perClient->hit('login:client:' . hash('sha256', $request->client));

    return $address && $client;
  }

  /* Held to the name the ask came under, whose cookie is the one at stake, and
     not to `config.php`'s, so a second name the server answers still logs in. The
     scheme is left out, since behind a TLS front the install sees plain HTTP. */
  private static function fromHere(Request $request): void
  {
    $origin = $request->header('Origin');
    if ($origin !== null && strcasecmp(explode('://', $origin, 2)[1] ?? '',
      explode('://', $request->origin(), 2)[1]) !== 0) {
      throw new CsrfRefused('the login was asked for from another site');
    }
  }

  /* Only a path of this house, so the login forwards nobody elsewhere. */
  /** @psalm-taint-escape header one slash, then no second, no backslash and no room for a line break */
  private static function path(string $to): ?string
  {
    return preg_match('#^/(?![/\\\\])\S*$#D', $to) === 1 ? $to : null;
  }

  private function address(?string $to, ?string $sentence = null, int $status = 200): Response
  {
    return $this->page(['step' => 'address', 'to' => $to, 'sentence' => $sentence, 'csrf' => ''], $status);
  }

  private function code(?string $sentence, int $status = 200): Response
  {
    return $this->page(['step' => 'code', 'to' => null, 'sentence' => $sentence, 'csrf' => $this->csrf->token()],
      $status);
  }

  /** @param array<string, ?string> $values */
  private function page(array $values, int $status): Response
  {
    return Response::html($this->template->render('pages/login', ['words' => $this->words,
      'note' => $this->premises->loginNote, 'link' => $this->premises->loginLink, ...$values]), $status);
  }
}
