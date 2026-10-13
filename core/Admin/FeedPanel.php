<?php

declare(strict_types=1);

namespace Admin;

use Http\Request;

/* What the feed panel shows on a screen (`view/partials/feed-panel.php`): the
   address the screen stands for, whole, since Copy takes exactly the string
   shown; whether it is a document, a collection or a record, which the four
   spellings are offered on; and whether the viewer left it open, which their
   browser says in a cookie so the page is drawn open from its first byte. */
final readonly class FeedPanel
{
  public const COOKIE = 'feed';

  public function __construct(
    public string $address,
    public bool $document,
    public bool $open,
  ) {}

  public static function at(Request $request, string $path, bool $document = false): self
  {
    return new self($request->origin() . $path, $document, $request->cookie(self::COOKIE) === 'open');
  }
}
