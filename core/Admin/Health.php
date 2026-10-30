<?php

declare(strict_types=1);

namespace Admin;

/* How a Settings card row that keeps the house running stands, its value the
   class its line is coloured by. A row with no state to judge has none; the
   files row is blocked while a release cannot be installed automatically. */
enum Health: string
{
  case Ok = 'is-ok';
  case Failing = 'is-failing';
  case Blocked = 'is-blocked';
}
