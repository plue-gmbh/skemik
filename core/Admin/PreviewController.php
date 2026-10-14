<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Http\Csrf;
use Http\Request;
use Http\Response;

/* `POST /preview` — a Markdown text drawn as the record screen reads it
   (`Prose`), for the preview beside the field while it is typed. The text is
   the body, since a `text` runs past what an address carries, and the answer
   is the fragment alone, its links to records chips this reader may open. It
   stores nothing; the token is asked all the same, so every `POST` the admin
   answers carries one. */
final readonly class PreviewController
{
  public function __construct(private Prose $prose, private Csrf $csrf) {}

  public function render(Account $account, Request $request): Response
  {
    $this->csrf->require($request);

    return Response::html($this->prose->markdown($account, $request->body));
  }
}
