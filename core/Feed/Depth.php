<?php

declare(strict_types=1);

namespace Feed;

/* How far down a document renders a record. Depth counts entities, and a list is
   not one: nested, it still renders its members, so a menu or a page's section
   arrives complete in one request. Its members render as members of a list, and a
   list among them keeps its own members as addresses, so no document grows by
   nesting lists in lists. */
enum Depth
{
  case Addressed;
  case Nested;
  case Listed;

  /* What a record at this depth renders its targets at, or null for their addresses. */
  public function below(bool $list): ?self
  {
    return match (true) {
      $this === self::Addressed => $list ? self::Listed : self::Nested,
      $this === self::Nested && $list => self::Listed,
      default => null,
    };
  }
}
