<?php

declare(strict_types=1);

namespace Storage;

/* What of the house a log row is about where it is no record: an account,
   named by its id, a key by its token's, a language by its tag, a collection
   by its slug, a dump by its run's id, since a read of everything must not be
   the quiet one. */
enum HouseItem: string
{
  case Account = 'account';
  case Key = 'key';
  case Locale = 'locale';
  case Collection = 'collection';
  case Dump = 'dump';
}
