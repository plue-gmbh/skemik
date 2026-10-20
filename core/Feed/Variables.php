<?php

declare(strict_types=1);

namespace Feed;

use Domain\Locale;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\SchemaOrg;
use Profile\Seed\Terms;

/* A field as a catalogue measures it, the one entry `Catalog` and `ProfileCatalog`
   both write, so a machine that learnt a field from the profile finds it under
   the same name and words in the catalogue. A value object is one entry of the
   property it arrives on, its keys under `valueReference`, since it is carried
   whole and its keys share their names across a type. What either document says
   of a field beyond that is its own. */
final readonly class Variables
{
  public function __construct(private SchemaOrg $schema, private Terms $terms) {}

  /**
   * @param list<FieldDefinition> $fields
   * @param \Closure(FieldDefinition, bool): array<string, mixed> $more what the document adds to a
   *   field's entry, or, flagged, to the entry of the value object the field is a key of
   * @return list<array<string, mixed>>
   */
  public function grouped(array $fields, Locale $locale, \Closure $more): array
  {
    $entries = [];
    foreach ($fields as $field) {
      $variable = [...$this->variable($field, $locale), ...$more($field, false)];
      if ($field->valueObjectOf === null) {
        $entries[$field->key] = $variable;
        continue;
      }
      $entries['@' . $field->valueObjectOf] ??= ['@type' => 'PropertyValue',
        'propertyID' => $field->valueObjectOf, 'name' => $field->block ?? $field->label,
        ...$more($field, true), 'valueReference' => []];
      $entries['@' . $field->valueObjectOf]['valueReference'][] = [...$variable,
        'propertyID' => (string) $this->schema->propertyName($field->key)];
    }

    return array_values($entries);
  }

  /* A select on the world's list names its standard, `ISO 4217`, and no code:
     the reader holds that table already.
     @return array<string, mixed> */
  public function variable(FieldDefinition $field, Locale $locale): array
  {
    $variable = ['@type' => 'PropertyValue',
      'propertyID' => (string) $this->schema->parameterName($field->key),
      'name' => $field->label];
    if ($field->kind !== FieldKind::Select) {
      return $variable;
    }

    return [...$variable, 'valueReference' => $field->list?->source() ?? $this->worded($field, $field->options,
      $locale)];
  }

  /* Each token of a closed list as a term by its code, with its word in the
     locale; a row key's stand in the profile alone, its rows' keys being entries
     there, where `valueReference` in the catalogue names a value object's keys.
     @param list<string> $tokens @return list<array<string, string>> */
  public function worded(FieldDefinition $field, array $tokens, Locale $locale): array
  {
    $address = fn (string $token): array => ($at = $this->schema->member($token)) === null ? [] : ['@id' => $at];

    return array_map(fn (string $token): array => ['@type' => 'DefinedTerm', ...$address($token),
      'termCode' => $token, 'name' => $this->terms->word($field, $token, $locale)], $tokens);
  }
}
