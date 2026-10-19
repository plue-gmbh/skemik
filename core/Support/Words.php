<?php

declare(strict_types=1);

namespace Support;

/* The chrome, one flat file per language under `language/`, English the source,
   and beside it `{language}.fields.php`, the thirty types' field names, and
   `{language}.terms.php`, what a select's options are called, the two things
   here a feed document carries, the field names English's own where none ships.
   A region's file, `de-CH.php` beside `de.php`, holds only the words its
   spelling differs in and is read first, word by word, then its language's, then
   English's; it ships as its chrome file, and its field names beside it as a
   language's do. Readable with no database, as the boot page renders through it
   in the browser's language. A language ships with a release: an update replaces
   `language/` whole, and a file a house put there is gone. A word a translation
   lacks is English's; a word English lacks and a placeholder handed no value
   halt a page under `zend.assertions`, and production shows the key or the brace
   rather than no page. Placeholders are named, `{found}`, so a translation may
   reorder them. A count is keyed by its form, `home.one.records` and
   `home.many.records`, and a language counting in three forms words the third
   under `few`, Polish's 2 to 4, which falls back to `many` where a translation
   has none. `language/` is not under `view/` because the boot page,
   `Auth\CodeDelivery` and `Http\ErrorHandler` read it too. */
final readonly class Words
{
  private const SOURCE = 'en';
  private const TAG = '/^([a-z]{2,3})(?:-[a-z]{4})?(?:-([a-z]{2}|\d{3}))?(?:-|$)/i';
  private const PLACEHOLDER = '/\{([a-z_]+)\}/';
  private const FORMS = '{n, plural, few{few} other{other}}';
  private const LANGUAGE_RANGE = '/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*)\s*'
    . '(?:;\s*q=([01](?:\.\d{0,3})?))?\s*$/i';

  /* As the reader asked it, `de-AT`, so every formatter writes that region's dates and numbers while the words
     are the files' it finds, `de.php` here: a region's file is for spelling alone, and most regions have none. */
  public string $language;

  /** @var array<string, string> */
  private array $source;

  /** @var array<string, string> */
  private array $words;

  /* The browser's language first, then the house's: a colleague whose browser
     asks for German reads German in a house set up in English, and the house's
     is what a browser naming no shipped language gets. */
  public function __construct(private string $directory, string $acceptLanguage, ?string $house = null)
  {
    $this->source = $this->load(self::SOURCE);
    $this->language = $this->firstShipped([...$this->accepted($acceptLanguage), $house]);
    $this->words = $this->language === self::SOURCE ? $this->source : $this->layered($this->language, '');
  }

  /** @param array<string, string> $values placeholder name without braces => value */
  public function get(string $key, array $values = []): string
  {
    $text = $this->words[$key] ?? $this->source[$key] ?? $key;
    assert(isset($this->source[$key]), sprintf('no English words for %s', $key));
    assert(array_diff($this->placeholders($this->source[$key] ?? $text), array_keys($values)) === [],
      sprintf('%s is missing a placeholder value', $key));
    $replacements = [];
    foreach ($values as $name => $value) {
      $replacements['{' . $name . '}'] = $value;
    }

    return strtr($text, $replacements);
  }

  /* A count in the form its number takes: `one` is exactly one, since its text
     spells the 1 out and French's category counts zero as one; `few` is intl's
     category of the number in this language, worded where the translation holds
     the key; and `many` is the rest. */
  public function count(string $group, string $noun, int $n): string
  {
    $few = $n !== 1 && \MessageFormatter::formatMessage($this->language, self::FORMS, ['n' => $n]) === 'few';
    $worded = $few ? $this->words[$group . '.few.' . $noun] ?? null : null;
    if ($worded !== null) {
      return strtr($worded, ['{n}' => (string) $n]);
    }

    return $this->get($group . '.' . ($n === 1 ? 'one' : 'many') . '.' . $noun, ['n' => (string) $n]);
  }

  /* The same chrome in another language, a content locale's: what a feed
     document asked rendered words a duration in. */
  public function in(string $tag): self
  {
    return new self($this->directory, '', $tag);
  }

  /** @return list<string> every language a file ships for, the source first */
  public function shipped(): array
  {
    return [self::SOURCE, ...array_values(array_diff($this->languages(), [self::SOURCE]))];
  }

  /* The field names of a language, asked by a chrome language or a content
     locale alike. Empty where none ships, English being the seed's own labels.
     @return array<string, string> by `key`, or `Type.key` where a type differs */
  public function fields(string $tag): array
  {
    return $this->layered($this->firstShipped([$tag]), '.fields');
  }

  /* Every shipped language's words for a select's options, empty where one ships
     no file, so `Profile\Seed\Terms` names what it lacks.
     @return array<string, array<string, string>> by language, then token */
  public function terms(): array
  {
    $terms = [];
    foreach ($this->shipped() as $language) {
      $terms[$language] = is_file($this->path($language . '.terms')) ? $this->load($language . '.terms') : [];
    }

    return $terms;
  }

  /* The first tag a language ships for, its region kept: `de-AT` though only `de.php` ships.
     @param list<?string> $tags */
  private function firstShipped(array $tags): string
  {
    foreach ($tags as $tag) {
      $spellings = $this->spellings($tag ?? '');
      if (array_any($spellings, fn (string $name): bool => is_file($this->path($name)))) {
        return $spellings[0];
      }
    }

    return self::SOURCE;
  }

  /** @return list<string> the tag as `language/` would name its region's file, then its language's */
  private function spellings(string $tag): array
  {
    if (preg_match(self::TAG, $tag, $parts) !== 1) {
      return [];
    }
    $language = strtolower($parts[1]);

    return isset($parts[2]) ? [$language . '-' . strtoupper($parts[2]), $language] : [$language];
  }

  /* A region's words over its language's, so `de-CH` says Strasse and German
     everywhere else; a tag its language ships for, so the language's file is
     there and the region's may not be.
     @return array<string, string> */
  private function layered(string $name, string $suffix): array
  {
    $words = [];
    foreach (array_reverse($this->spellings($name)) as $file) {
      $words = is_file($this->path($file . $suffix)) ? [...$words, ...$this->load($file . $suffix)] : $words;
    }

    return $words;
  }

  /** @return list<string> ranked by the header's q, ties in written order */
  private function accepted(string $header): array
  {
    $ranked = [];
    foreach (explode(',', $header) as $range) {
      $matched = preg_match(self::LANGUAGE_RANGE, $range, $parts);
      $quality = (float) ($parts[2] ?? 1);
      if ($matched === 1 && $quality > 0) {
        $ranked[] = [$parts[1], $quality];
      }
    }
    usort($ranked, fn (array $a, array $b): int => $b[1] <=> $a[1]);

    return array_column($ranked, 0);
  }

  /** @return array<string, string> */
  private function load(string $language): array
  {
    $path = $this->path($language);
    $words = is_file($path) ? require $path : null;
    $valid = is_array($words)
      && array_filter($words, is_string(...)) === $words
      && array_filter(array_keys($words), is_string(...)) === array_keys($words);

    return $valid ? $words : throw WordsFailed::malformed($path);
  }

  private function path(string $language): string
  {
    return $this->directory . '/' . $language . '.php';
  }

  /** @return list<string> sorted, since a translation may reorder them */
  private function placeholders(string $text): array
  {
    preg_match_all(self::PLACEHOLDER, $text, $found);
    $names = array_unique($found[1]);
    sort($names);

    return $names;
  }

  /** @return list<string> the languages' chrome files, neither a region's nor anybody's field names */
  private function languages(): array
  {
    $names = array_map(fn (string $path): string => basename($path, '.php'),
      glob($this->directory . '/*.php') ?: []);

    return array_values(array_filter($names, fn (string $name): bool => preg_match('/^[a-z]{2,3}$/', $name) === 1));
  }
}
