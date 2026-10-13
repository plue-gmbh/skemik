<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Storage\Collection;

/* A collection address as a table, a record address as one row, rendered from
   the JSON-LD document so the gates run once. An edge's cell is its address
   relative to the file's route, `organizations/acme`: the absolute `@id` costs 78
   characters a cell its reader would strip, a bare slug is ambiguous, and a name
   goes stale. Edge qualifiers do not survive the flattening, which is said rather
   than discovered, and a value object is its values in one cell. A byte order
   mark, since Excel misreads a table without one, and a text cell starting like a
   formula takes a leading `'`, since a channel's `=HYPERLINK(…)` would run in the
   owner's sheet. */
final readonly class Csv
{
  private const WRAPPERS = ['ListItem', 'Role', 'ProgramMembership'];
  private const BYTE_ORDER_MARK = "\u{FEFF}";
  private const FORMULA = '/^[=+\-@\t\r]/';

  public function __construct(private JsonLd $document, private Address $address) {}

  public function contentType(): string
  {
    return 'text/csv; charset=utf-8';
  }

  public function record(Entity $entity, Collection $collection): string
  {
    return $this->table($this->document->columns($collection),
      [$this->document->recordDocument($entity, $collection)]);
  }

  /** @param list<Entity> $members */
  public function listing(array $members, Collection $collection, int $total, string $address): string
  {
    $list = $this->document->listDocument($members, $collection, $total, $address);
    /** @param array<string, mixed> $item */
    $member = fn (array $item): array => is_array($item['item'] ?? null) ? $item['item'] : [];

    return $this->table($this->document->columns($collection),
      array_map($member, $list['itemListElement']));
  }

  /** @param list<string> $columns @param list<array<string, mixed>> $documents */
  private function table(array $columns, array $documents): string
  {
    $rows = [$this->row($columns)];
    foreach ($documents as $document) {
      assert(array_diff(array_keys($document), $columns, ['@context', '@type']) === [],
        'a document carries a property its table has no column for');
      $cells = fn (string $key): string => $key === '@id'
        ? $this->address->of((string) ($document[$key] ?? ''))
        : $this->text($document[$key] ?? null);
      $rows[] = $this->row(array_map($cells, $columns));
    }

    return self::BYTE_ORDER_MARK . implode("\r\n", $rows) . "\r\n";
  }

  /* A number is never a formula, however it starts. */
  private function text(mixed $value): string
  {
    $cell = $this->cell($value);

    return !is_int($value) && !is_float($value) && preg_match(self::FORMULA, $cell) === 1
      ? "'" . $cell
      : $cell;
  }

  private function cell(mixed $value): string
  {
    if (is_array($value)) {
      return array_is_list($value)
        ? implode(' ', array_map($this->cell(...), $value))
        : $this->flat($value);
    }

    return match (true) {
      is_bool($value) => $value ? 'true' : 'false',
      $value === null => '',
      default => (string) $value,
    };
  }

  /* A wrapper is unwrapped to what it wraps, which is the one array-valued key
     it has; everything else in it is the qualifiers that do not survive here.
     @param array<string, mixed> $object */
  private function flat(array $object): string
  {
    if (isset($object['@id'])) {
      return $this->address->of((string) $object['@id']);
    }
    $wrapped = in_array($object['@type'] ?? '', self::WRAPPERS, true);
    unset($object['@type']);
    $values = $wrapped ? array_filter($object, is_array(...)) : $object;

    return implode(' ', array_map($this->cell(...), $values === [] ? $object : $values));
  }

  /** @param list<string> $cells */
  private function row(array $cells): string
  {
    $quoted = fn (string $cell): string => preg_match('/["\r\n,]/', $cell) === 1
      ? '"' . str_replace('"', '""', $cell) . '"'
      : $cell;

    return implode(',', array_map($quoted, $cells));
  }
}
