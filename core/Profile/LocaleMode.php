<?php

declare(strict_types=1);

namespace Profile;

/* How this type carries a second language. The question that
   separates them is whether the words are the thing or the thing has words — a
   translated article is a second article, a translated Offer is a split
   inventory and a second shop selling the same last seat.
   Beside the type because the alternative was three lists of type names typed
   into two Domain classes, a second copy of what the seed says. */
enum LocaleMode: string
{
  case Fork = 'fork';          // works: one row per language, its own slug and status
  case Localise = 'localise';  // labelled things: one row, free-text fields per locale
  case Both = 'both';          // MediaObject and Dataset — fork when the bytes differ, localise when not
  case None = 'none';          // instruments and other people's words: a second language is a second one

  public function forks(): bool
  {
    return $this === self::Fork || $this === self::Both;
  }

  public function localises(): bool
  {
    return $this === self::Localise || $this === self::Both;
  }

  /* All but a work, whose record is its locale's alone. The two that do both
     answer everywhere too: a photograph read under `en-GB` is that address's.
     Its `inLanguage` stays the row's, being the language of the bytes: English
     words on a German price list label a German file, and an English file is a
     fork. */
  public function answersOnEveryLocale(): bool
  {
    return $this !== self::Fork;
  }
}
