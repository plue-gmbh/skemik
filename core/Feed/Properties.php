<?php

declare(strict_types=1);

namespace Feed;

use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\Qualifier;
use Profile\SchemaOrg;
use Profile\TypeDefinition;
use Support\Markup;
use Support\Rendered;

/* A record's values under their schema.org names, value objects assembled here
   and nowhere else: the nesting is serialisation knowledge, which is what keeps
   every key of one sortable and filterable at its own address. A key that names
   no property serves every use of its object on the type, an invoice's currency
   carried by both amounts. `JsonLd` writes a document with it and `Csv` takes
   its columns from it, so a table's header is the document's shape. Given a
   `Markup`, the values leave rendered: a Markdown field or row key as HTML, a
   long text as paragraphs, a text's inline marks, and a duration in the
   renderer's words, in the one pass that knows each value's kind. */
final readonly class Properties
{
  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private ?Markup $markup = null,
  ) {}

  /** @param array<string, mixed> $values by field key
      @return array<string, mixed> */
  public function of(TypeDefinition $type, array $values): array
  {
    $properties = [];
    $objects = [];
    foreach ($values as $key => $value) {
      $field = $type->field($key);
      if ($field === null || in_array($value, [null, []], true)) {
        continue;
      }
      $value = $field->cardinality->isEdge() ? $value : $this->rendered($field, $value);
      $name = (string) $this->schema->propertyName($key);
      if ($field->valueObject === null
        || ($field->valueObjectOf === null && $this->schema->belongsTo($name, $type->name))) {
        $properties[$name] = $value;
      }
      if ($field->valueObject !== null) {
        $objects[(string) $field->valueObjectOf][$field->valueObject]
          [(string) $field->valueObjectKey] = $value;
      }
    }

    return [...$properties, ...$this->nested($objects)];
  }

  /* Every name a document of these fields may carry, in the order it carries
     them, whatever one record holds.
     @param list<string> $keys @return list<string> */
  public function names(string $type, array $keys): array
  {
    return array_keys($this->of($this->profile->byType($type), array_fill_keys($keys, true)));
  }

  /* A property's name in the words of the profile this was built with, and an
     object's the heading its flattened keys stand under, `Address`.
     @return array<string, string> by property */
  public function labels(string $type): array
  {
    $labels = [];
    foreach ($this->profile->byType($type)->fields() as $field) {
      $nests = $field->valueObject !== null && $field->valueObjectOf !== null;
      $name = $nests ? (string) $field->valueObjectOf : (string) $this->schema->propertyName($field->key);
      $labels[$name] ??= $nests ? $field->block ?? $field->label : $field->label;
    }

    return $labels;
  }

  /** @param array<string, array<string, array<string, mixed>>> $objects property, object, key
      @return array<string, mixed> */
  private function nested(array $objects): array
  {
    $shared = $objects[''] ?? [];
    unset($objects['']);
    $nested = [];
    foreach ($objects as $property => $keys) {
      foreach ($keys as $object => $filled) {
        $nested[$property] = ['@type' => $object, ...$filled, ...($shared[$object] ?? [])];
      }
    }

    return $nested;
  }

  /* A word, a number and a date leave as they are; a repeatable text is rendered
     one by one and a row's keys by the kinds its value object declares.
     @param string|int|float|bool|array<array-key, mixed>|null $value
     @return string|int|float|bool|array<array-key, mixed>|Rendered|null */
  private function rendered(FieldDefinition $field, string|int|float|bool|array|null $value):
    string|int|float|bool|array|Rendered|null
  {
    if ($this->markup === null) {
      return $value;
    }
    if ($field->kind === FieldKind::Objects && is_array($value)) {
      $kinds = $this->profile->valueObject((string) $field->ref)?->kinds ?? [];

      return array_map(fn (array $row): array => $this->row($row, $kinds), $value);
    }

    return is_array($value)
      ? array_map(fn (string|int|float|bool|array|null $one): string|int|float|bool|array|Rendered|null
        => $this->one($field->kind, $one), $value)
      : $this->one($field->kind, $value);
  }

  /* An edge's own words, each read as the kind `Qualifier` gives it, its buttons as rows.
     @param array<string, mixed> $qualifiers @return array<string, mixed> */
  public function qualified(array $qualifiers): array
  {
    $kinds = $this->profile->valueObject('Action')?->kinds ?? [];
    foreach ($this->markup === null ? [] : $qualifiers as $key => $held) {
      $qualifiers[$key] = is_array($held)
        ? array_map(fn (mixed $row): mixed => is_array($row) ? $this->row($row, $kinds) : $row, $held)
        : $this->one(Qualifier::tryFrom((string) $key)?->kind() ?? FieldKind::Text, $held);
    }

    return $qualifiers;
  }

  /* A term's code where an edge names it, rendered as the term's own record
     renders its `term_code`, a text: the code is what a site prints for a tag.
     An edge's values arrive built, its targets' documents rendered at their own
     depth and a term's code here, so `of` leaves them as they are. */
  public function code(string $code): string|Rendered
  {
    return $this->markup?->line($code) ?? $code;
  }

  /* A key no kind is declared for is a short text, and a word standing where a
     record could is its name, so no string of a rendered document is left for
     its reader to escape.
     @param array<string, mixed> $row @param array<string, FieldKind> $kinds @return array<string, mixed> */
  private function row(array $row, array $kinds): array
  {
    foreach ($row as $key => $held) {
      $row[$key] = match (true) {
        str_starts_with((string) $key, '@') => $held,
        is_array($held) && !isset($held['@id']) && is_string($held['name'] ?? null)
          => [...$held, 'name' => $this->one(FieldKind::Text, $held['name'])],
        default => $this->one($kinds[$key] ?? FieldKind::Text, $held),
      };
    }

    return $row;
  }

  /** @param string|int|float|bool|array<array-key, mixed>|null $one
      @return string|int|float|bool|array<array-key, mixed>|Rendered|null */
  private function one(FieldKind $kind, string|int|float|bool|array|null $one):
    string|int|float|bool|array|Rendered|null
  {
    return match (true) {
      !is_string($one) || $this->markup === null => $one,
      $kind === FieldKind::Markdown => $this->markup->prose($one),
      $kind === FieldKind::LongText => $this->markup->text($one),
      $kind === FieldKind::Text => $this->markup->line($one),
      $kind === FieldKind::Duration => $this->markup->duration($one),
      default => $one,
    };
  }
}
