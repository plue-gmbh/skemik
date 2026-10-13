<?php

declare(strict_types=1);

namespace Update;

/* What a snapshot was taken for, carried in its folder's name: the day's own,
   the owner's press, the holdings a restore displaced, or the ones an update
   found. */
enum Occasion: string
{
  case Daily = 'daily';
  case Manual = 'manual';
  case Restore = 'restore';
  case Update = 'update';
}
