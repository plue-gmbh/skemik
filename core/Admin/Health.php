<?php

declare(strict_types=1);

namespace Admin;

/* How a Settings card row that keeps the house running stands, its value the
   class its line is coloured by. A row with no state to judge has none. */
enum Health: string
{
  case Ok = 'is-ok';
  case Failing = 'is-failing';
}
