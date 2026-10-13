<?php

declare(strict_types=1);

namespace Auth;

/* What a machine may do. Three are about records, and no fifth: a stock
   decrement or a reservation is a `set` carrying `If-Match`, not a verb of its
   own. `arrange` is about the collection, never a record: which fields it
   carries and lets out (`Ingest\ArrangeController`), so a script sets a fresh
   install up in minutes. Apart from `Capability` because a token never reaches
   a screen and an account never writes through the address. */
enum GrantCapability: string
{
  case Create = 'create';
  case Set = 'set';
  case Read = 'read';
  case Arrange = 'arrange';
}
