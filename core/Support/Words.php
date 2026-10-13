<?php

declare(strict_types=1);

namespace Support;

/* The chrome, one flat file per language under `language/`, English the source,
   and beside it `{language}.fields.php`, the thirty types' field names, and
   `{language}.terms.php`, what a select's options are called, the two things
   here a feed document carries, the field names English's own where none ships.
   Readable with no database, as the boot page renders through it in the
   browser's language. A language ships with a release: an update replaces
   `language/` whole, and a file a house put there is gone. A word a translation
   lacks is English's; a word English lacks and a placeholder handed no value
   halt a page under `zend.assertions`, and production shows the key or the brace
   rather than no page. Placeholders are named, `{found}`, so a translation may
   reorder them. `language/` is not under `view/` because the boot page,
   `Auth\CodeDelivery` and `Http\ErrorHandler` read it too. */
final readonly class Words
{
  private const SOURCE = 'en';
  private const PLACEHOLDER = '/\{([a-z_]+)\}/';
  private const LANGUAGE_RANGE = '/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*)\s*'
    . '(?:;\s*q=([01](?:\.\d{0,3})?))?\s*$/i';

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
    $this->words = $this->language === self::SOURCE ? $this->source : $this->load($this->language);
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
    $language = $this->firstShipped([$tag]);

    return is_file($this->path($language . '.fields')) ? $this->load($language . '.fields') : [];
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

  /** @param list<?string> $tags */
  private function firstShipped(array $tags): string
  {
    foreach ($tags as $tag) {
      $language = strtolower(explode('-', $tag ?? '')[0]);
      if (preg_match('/^[a-z]{2,3}$/', $language) === 1 && is_file($this->path($language))) {
        return $language;
      }
    }

    return self::SOURCE;
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

  /** @return list<string> the chrome files, a language's field names not among them */
  private function languages(): array
  {
    $names = array_map(fn (string $path): string => basename($path, '.php'),
      glob($this->directory . '/*.php') ?: []);

    return array_values(array_filter($names, fn (string $name): bool => !str_contains($name, '.')));
  }
}
