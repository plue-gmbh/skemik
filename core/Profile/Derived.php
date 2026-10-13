<?php

declare(strict_types=1);

namespace Profile;

/* What computes a `∂` value. There are two shapes and they differ only in
   what they read: the spine's own column, and an `f` over an edge — five
   functions and the price range, which was a mark of its own until it read the
   offers that name a record as a rating reads its reviews, and no seventh,
   because that is the query language the mark exists not to grow. A word count
   and a reading time were the third and left: on a course or a
   how-to `timeRequired` is the time the thing takes, which no text measures.
   One enum rather than two marks: a reader needs to know only that nobody
   typed the value. */
enum Derived: string
{
  case Locale = 'locale';             // the spine — `inLanguage`, and nothing else
  case Count = 'count';
  case Min = 'min';
  case Max = 'max';
  case Sum = 'sum';
  case Avg = 'avg';
  case Range = 'range';               // an `AggregateOffer` per currency, over the offers' prices

  public function readsEdge(): bool
  {
    return in_array($this, [self::Count, self::Min, self::Max, self::Sum, self::Avg, self::Range], true);
  }
}
