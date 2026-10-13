<?php

declare(strict_types=1);

namespace Profile;

/* Whether a purge of this type's record warns first. What else an erasure
   request would do to a type waits for its screen, and a mark nothing reads is
   one nobody checks. */
enum Erasure
{
  case Purge;  // nothing holds the row back once the owner purges it
  case Hold;   // a retention duty usually does: the purge warns first, and the owner decides
}
