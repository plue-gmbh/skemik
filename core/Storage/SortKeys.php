<?php

declare(strict_types=1);

namespace Storage;

use Domain\LocalisedText;
use Domain\Props;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\TypeDefinition;
use Support\Json;

/* The derived sort column: one DIN 5007-1 key per text field, so plain `binary`
   order is German dictionary order and `är` finds `Ärztin`. Written in PHP
   because SQLite has neither casefold nor transliteration. The four German folds
   come first, since intl would make `ä` into `ae`. One folding per install, not
   per locale: a Swedish house sorts `ä` with `a`, and reversing that is one
   rewrite of this column. Only the short `text` kind is keyed, and rows by the
   `value` each holds: a long text is searched through the word table and never
   sorted, and a copy of every article body is not a key. */
final readonly class SortKeys
{
  private const GERMAN = ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss'];

  public function __construct(private Json $json = new Json())
  {
    assert($this->key('Ärztin') < $this->key('Zimmermann'), 'an umlaut sorts after z');
    assert($this->key('Łódź ØRSTED đak Straße') === 'lodz orsted dak strasse', 'a letter is not folded');
  }

  public function of(TypeDefinition $type, Props $props): string
  {
    $document = $props->document();
    $keys = $this->keyed($type, $document);
    $translations = array_filter((array) ($document[LocalisedText::KEY] ?? []), is_array(...));
    foreach ($translations as $tag => $words) {
      $keyed = $this->keyed($type, $words);
      if ($keyed !== []) {
        $keys[LocalisedText::KEY][$tag] = $keyed;
      }
    }

    return $this->json->encode((object) $keys);
  }

  /** @param array<array-key, mixed> $document the authored values, or one locale's words
      @return array<string, string|list<string>> */
  private function keyed(TypeDefinition $type, array $document): array
  {
    $keys = [];
    foreach ($type->fields() as $field) {
      $keyed = self::keys($field) || self::listed($field);
      $entries = $keyed ? self::entries($field, $document[$field->key] ?? null) : [];
      if ($entries !== []) {
        $keys[$field->key] = self::keys($field) ? $this->key($entries[0]) : array_map($this->key(...), $entries);
      }
    }

    return $keys;
  }

  /* `Query` reads the key wherever this writes it, so both ask here. */
  public static function keys(FieldDefinition $field): bool
  {
    return $field->kind === FieldKind::Text && $field->cardinality === Cardinality::S && !$field->repeatable;
  }

  public static function listed(FieldDefinition $field): bool
  {
    return $field->cardinality === Cardinality::S
      && (($field->kind === FieldKind::Text && $field->repeatable) || $field->kind === FieldKind::Objects);
  }

  /* The strings a value is keyed and searched by: a text, each of a repeatable
     one, and of rows the `value` each holds, the key schema.org gives the
     objects that are one value, PropertyValue among them. So an identifier is
     found as a text is, while a row's name stands as a field's word does and
     is no more searched than that.
     @return list<string> */
  public static function entries(FieldDefinition $field, mixed $value): array
  {
    $held = is_array($value) && array_is_list($value) ? $value : [$value];
    $strings = $field->kind !== FieldKind::Objects ? $held
      : array_map(fn (mixed $row): mixed => is_array($row) ? $row['value'] ?? null : null, $held);

    return array_values(array_filter($strings, fn (mixed $one): bool => is_string($one) && $one !== ''));
  }

  public function key(string $text): string
  {
    $folded = strtr(mb_convert_case($text, MB_CASE_LOWER, 'UTF-8'), self::GERMAN);
    $latin = \Transliterator::create('Latin-ASCII')?->transliterate($folded);

    return mb_convert_case($latin === false || $latin === null ? $folded : $latin, MB_CASE_FOLD, 'UTF-8');
  }
}
