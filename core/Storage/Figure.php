<?php

declare(strict_types=1);

namespace Storage;

/* What a saved question asks of its narrowed collection: how many records, a
   number field summed, or that field on the record its collection's date names
   last. No average, since a rating is derived per record and a collection's
   would be a new public figure. */
enum Figure: string
{
  case Count = 'count';
  case Sum = 'sum';
  case Latest = 'latest';
}
