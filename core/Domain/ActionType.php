<?php

declare(strict_types=1);

namespace Domain;

/* What an `activity` row records, in schema.org's own names. Closed to what
   the app writes, so a case arrives with the code that writes it.
   Only a removal is a `DeleteAction`: putting a record in the trash and taking it
   out again are updates of `deleted_at`, recorded as its `from` and `to` the way
   a rename records its slug, so the protocol tells an undo from an erasure. */
enum ActionType: string
{
  case Create = 'CreateAction';
  case Update = 'UpdateAction';
  case Delete = 'DeleteAction';
}
