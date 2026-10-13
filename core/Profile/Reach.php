<?php

declare(strict_types=1);

namespace Profile;

/* Whether a type has a public state at all. The strict test is whether any
   install would ever publish one: an invoice, an order, a reservation and
   somebody's message answer no, and the editor offers no state a type cannot
   reach. Beside the type, and neither defaulted, because the alternative was a
   list of names in `Domain\Status` that a thirty-first type would not be on. */
enum Reach: string
{
  case Public = 'public';  // may be published, and the address serves it once it is
  case House = 'house';    // draft, review and archived only: nothing of it leaves the house
}
