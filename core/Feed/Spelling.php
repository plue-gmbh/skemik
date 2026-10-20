<?php

declare(strict_types=1);

namespace Feed;

use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Kinds;
use Profile\Profile;
use Profile\SchemaOrg;

/* One spelling of a value at the address and one in `props`, so the spelling a
   document shows finds its row and a write is held as the editor holds it. A
   link under the house's origin is held as its path and shown whole again, so a
   house that moves its domain keeps every link. A fixed list's value is held as
   its token, which a filter names and a table sorts, and the JSON-LD document
   shows it as the member's address, which schema.org's own examples name
   (`SchemaOrg::member`); the flat formats keep the token. The two doors,
   `FeedRequest` and `JsonLd` ask here, so a value is one spelling wherever it
   enters and one wherever it leaves. */
final readonly class Spelling
{
  private const SCHEMA = 'https://schema.org/';

  public function __construct(
    private SchemaOrg $schema,
    private Profile $profile,
    private string $origin,
    private bool $addressed,
  ) {}

  /** @param string|int|float|bool|array<array-key, mixed>|null $value
      @return string|int|float|bool|array<array-key, mixed>|null */
  public static function held(?FieldDefinition $field, string|int|float|bool|array|null $value,
    string $origin): string|int|float|bool|array|null
  {
    $path = fn (string|int|float|bool|array|null $link): string|int|float|bool|array|null
      => !is_string($link) ? $link : (str_starts_with($link = Kinds::spelled($link), $origin . '/')
        ? substr($link, strlen($origin)) : $link);

    return match ($field?->kind) {
      FieldKind::Link => is_array($value) ? array_map($path, $value) : $path($value),
      FieldKind::Select => is_string($value) ? self::token($value) : $value,
      default => $value,
    };
  }

  /* The token a write may send as the member's address, a row's key among them. */
  public static function token(string $value): string
  {
    return str_starts_with($value, self::SCHEMA) ? substr($value, strlen(self::SCHEMA)) : $value;
  }

  /** @param string|int|float|bool|array<array-key, mixed>|null $value
      @return string|int|float|bool|array<array-key, mixed>|null */
  public function shown(FieldDefinition $field, string|int|float|bool|array|null $value):
    string|int|float|bool|array|null
  {
    $whole = fn (string|int|float|bool|array|null $link): string|int|float|bool|array|null
      => is_string($link) && str_starts_with($link, '/') ? $this->origin . $link : $link;

    return match (true) {
      $field->kind === FieldKind::Link => is_array($value) ? array_map($whole, $value) : $whole($value),
      $field->kind === FieldKind::Select && $this->addressed && is_string($value)
        => $this->schema->member($value) ?? $value,
      default => $value,
    };
  }

  /* A row's key on a fixed list is spelt as a select's value is.
     @param array<string, mixed> $row @return array<string, mixed> */
  public function row(FieldDefinition $field, array $row): array
  {
    $options = $this->addressed ? $this->profile->valueObject((string) $field->ref)?->options ?? [] : [];
    foreach (array_keys($options) as $key) {
      if (is_string($row[$key] ?? null)) {
        $row[$key] = $this->schema->member($row[$key]) ?? $row[$key];
      }
    }

    return $row;
  }
}
