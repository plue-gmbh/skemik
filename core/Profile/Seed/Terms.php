<?php

declare(strict_types=1);

namespace Profile\Seed;

use Domain\Locale;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;

/* The words for a select's tokens, per content language: what a person reads
   where the holdings store and emit `EventCancelled`, from `language/`'s
   `{language}.terms.php`, whose header says why they are files of their own. */
final readonly class Terms
{
  private const SOURCE = 'en';

  /** @param array<string, array<string, string>> $words by language, then token (`Support\Words::terms`) */
  public function __construct(Profile $profile, private array $words)
  {
    assert($this->unworded($profile) === [], 'a select token has no word in every shipped language');
  }

  /* A code from the world's list is named by `intl`, a token of the profile's by
     the files. */
  public function word(FieldDefinition $field, string $token, Locale $locale): string
  {
    if ($field->list !== null) {
      return $field->list->word($token, $locale);
    }
    $language = explode('-', $locale->tag)[0];

    return $this->words[$language][$token] ?? $this->words[self::SOURCE][$token] ?? $token;
  }

  /** @return list<string> tokens some shipped language lacks, as language:token */
  private function unworded(Profile $profile): array
  {
    $missing = [];
    foreach ($profile->types() as $type) {
      foreach ($type->fields() as $field) {
        $options = $field->kind === FieldKind::Select ? $field->options : [];
        foreach ($options as $token) {
          foreach ($this->words as $language => $words) {
            isset($words[$token]) || $missing[] = $language . ':' . $token;
          }
        }
      }
    }

    return $missing;
  }
}
