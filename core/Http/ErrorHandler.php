<?php

declare(strict_types=1);

namespace Http;

use Admin\Reasons;
use Auth\AccountRefused;
use Auth\NotAuthenticated;
use Auth\NotPermitted;
use Domain\Exceptions\AskedTooMuch;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\ForbiddenTransition;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\SlugTaken;
use Domain\Exceptions\StaleWrite;
use Domain\Exceptions\TargetRequired;
use Domain\Exceptions\ValueTaken;
use Support\Json;
use Support\Template;
use Support\UnreadableTime;
use Support\Words;

/* Domain exceptions as status codes, in one place, for three audiences: a feed
   caller gets the status and one sentence naming the field where there is one, a
   person the boot failure's page in their chrome language, and `Logger`
   everything; nobody sees a stack trace. The incident id is the point of the
   split: six characters on the page and in the log, so *there's an error* is one
   `grep` rather than a reproduction, the support call the hosting margin is made
   of. A logged-out person asking for a page is sent to the login, the next step
   either way. Only a `500` is logged, since a `404` is a visitor's typo. */
final readonly class ErrorHandler
{
  public function __construct(private Logger $logger, private Template $template, private Words $words) {}

  public function handle(\Throwable $failure, Request $request, ?string $actor): Response
  {
    $status = $this->status($failure);
    /* A malformed value in a read is a malformed request — the unknown field of
       `Feed\FeedRequest`'s 400, named in the sentence; in a write it is a payload
       the house understood and refuses. */
    $status = $status === 422 && $request->reads() ? 400 : $status;
    /* The one address family whose caller is a machine whatever it sends: a
       reader asking for `.csv` gets its refusal as JSON rather than a page, and
       a refused key as a `401` rather than as the login screen. */
    $machine = $request->wantsJson() || str_starts_with($request->path, '/feed');
    if ($failure instanceof NotAuthenticated && $request->reads() && !$machine) {
      return $this->toLogin($request);
    }
    $incident = $status >= 500 ? $this->incident() : null;
    if ($incident !== null) {
      $this->logger->failure($incident, $status, $request->method . ' ' . $request->path, $failure, $actor);
    }
    $sentence = $this->sentence($status, $incident, $failure);
    if ($machine) {
      $detail = $status >= 500 ? $sentence : $failure->getMessage();

      return Response::json((new Json())->encode(['error' => $detail, 'incident' => $incident]), $status);
    }

    return Response::html($this->template->render('pages/failure', ['words' => $this->words,
      'sentence' => $sentence]), $status);
  }

  private function toLogin(Request $request): Response
  {
    $asked = $request->path . ($request->query === [] ? '' : '?' . http_build_query($request->query));

    return Response::redirect($asked === '/' ? '/login' : '/login?' . http_build_query(['to' => $asked]));
  }

  private function status(\Throwable $failure): int
  {
    return match (true) {
      $failure instanceof UnreadableText => 400,
      $failure instanceof BodyTooLarge => 413,
      $failure instanceof RouteNotFound, $failure instanceof EntityNotFound => 404,
      $failure instanceof NotAuthenticated => 401,
      $failure instanceof NotPermitted, $failure instanceof CsrfRefused => 403,
      $failure instanceof StaleWrite => 412,
      $failure instanceof PreconditionRequired => 428,
      $failure instanceof TooManyRequests => 429,
      $failure instanceof MalformedValue, $failure instanceof AskedTooMuch, $failure instanceof ValueTaken,
      $failure instanceof SlugTaken, $failure instanceof ForbiddenTransition, $failure instanceof UnreadableTime,
      $failure instanceof AccountRefused, $failure instanceof TargetRequired => 422,
      default => 500,
    };
  }

  private function sentence(int $status, ?string $incident, \Throwable $failure): string
  {
    return $this->named($failure) ?? match ($status) {
      404 => $this->words->get('failure.not_found'),
      401, 403 => $this->words->get('failure.refused'),
      500 => $this->words->get('failure.incident', ['incident' => (string) $incident]),
      default => $this->words->get('failure.request'),
    };
  }

  /* The record screen offers no delete for a person holding an account, so this
     is a request made past it, and still answered with what to do first; a
     purge a required edge refuses says which records, a translation whose
     locale the trash holds answers as a held `!` value does, on a page as in
     the sheet, and a question too large names its limit, since a search box is
     where a person types one. A form from an old page or another site says so,
     since a visitor holding no account meets it at the login too. */
  private function named(\Throwable $failure): ?string
  {
    return match (true) {
      $failure instanceof CsrfRefused => $this->words->get('failure.reload'),
      $failure instanceof AccountRefused && $failure->held !== null
        => $this->words->get('record.held', ['email' => $failure->held->email]),
      $failure instanceof TargetRequired => $this->words->get('purge.required',
        ['record' => $failure->record, 'records' => implode(', ', $failure->records)]),
      $failure instanceof ValueTaken => Reasons::worded($this->words, $failure->violation),
      $failure instanceof AskedTooMuch => $failure->filter === null
        ? $this->words->get('failure.search_words', ['limit' => (string) $failure->limit])
        : $this->words->get('failure.filter_values', ['filter' => $failure->filter,
          'limit' => (string) $failure->limit]),
      default => null,
    };
  }

  /* Six characters from an alphabet a person can read aloud over the phone. */
  private function incident(): string
  {
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $incident = '';
    for ($index = 0; $index < 6; $index++) {
      $incident .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $incident;
  }
}
