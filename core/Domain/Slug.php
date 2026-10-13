<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;

/* Slugification and the collision suffix, not uniqueness checking — that needs
   the database and lives in the repository. A slug has no history: renaming frees
   the old one, and what a rename leaves behind is a line in the activity log.
   German letters fold the way a German reader spells them in an address,
   `muenchen` rather than `munchen`; everything else goes through intl's Latin
   transliteration, so `Łódź` and a Cyrillic name get a slug too. The fallback is
   for a name nothing transliterates, never a reason to refuse the record. */
final readonly class Slug
{
  private const SHAPE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
  private const GERMAN = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
    'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ẞ' => 'SS'];

  public function __construct(public string $value)
  {
    if (preg_match(self::SHAPE, $value) !== 1) {
      throw MalformedValue::named('slug', $value);
    }
  }

  public static function from(string $text, string $fallback): self
  {
    $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
    $latin = $transliterator?->transliterate(strtr($text, self::GERMAN));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $latin)), '-');

    return new self($slug === '' ? $fallback : $slug);
  }

  /* The second record named `Till` becomes `till-2`; `Storage\Collisions` counts. */
  public function numbered(int $number): self
  {
    assert($number >= 2, 'the first record keeps the bare slug');

    return new self($this->value . '-' . $number);
  }
}
