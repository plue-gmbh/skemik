<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Storage\Collection;
use Support\Markup;

/* A record address as a document — heading, body, its edges as links — and a
   collection address as a linked list ending in its next page; the shape rule
   is in `Html`, the edge address in `Csv`. The same serialiser the dump writes with, written once.

   The body is the record's `text`, which is the one Markdown field the profile
   declares, and it is passed through rather than rendered: this format is the
   one whose syntax it is already written in, a link to a record going on to its
   `.md` as every other link here does. Nothing here is in the house's
   chrome language — a reader of this file is a machine or somebody reading a
   dump, and neither is on the admin's screen. */
final readonly class Markdown
{
  public function __construct(private JsonLd $document, private Address $address) {}

  public function contentType(): string
  {
    return 'text/markdown; charset=utf-8';
  }

  public function record(Entity $entity, Collection $collection): string
  {
    return $this->page($this->document->recordDocument($entity, $collection));
  }

  /** @param list<Entity> $members */
  public function listing(array $members, Collection $collection, int $total, string $address): string
  {
    $list = $this->document->listDocument($members, $collection, $total, $address);
    $lines = ['# ' . $list['name'], '', sprintf('%d/%d', count($list['itemListElement']), $total), ''];
    foreach ($list['itemListElement'] as $item) {
      $member = is_array($item['item'] ?? null) ? $item['item'] : [];
      $lines[] = '- ' . $this->link($member);
    }
    $next = Address::next($address, $total);
    $onward = $next === null ? [] : ['', '[→](' . $this->address->of($next) . ')'];

    return implode("\n", [...$lines, ...$onward]) . "\n";
  }

  /** @param array<string, mixed> $document */
  private function page(array $document): string
  {
    $text = Markup::relinked((string) ($document['text'] ?? ''), $this->address->of(...));
    $heading = ['# ' . $this->heading($document), ''];
    $lines = $text === '' ? $heading : [...$heading, $text, ''];
    foreach ($document as $property => $value) {
      if (!in_array($property, ['@context', '@id', '@type', 'text', 'name', 'headline'], true)) {
        $lines[] = sprintf('**%s**: %s', $property, $this->value($value));
      }
    }
    $lines[] = '';
    $lines[] = '[' . $document['@id'] . '](' . $document['@id'] . ')';

    return implode("\n", $lines) . "\n";
  }

  private function value(mixed $value): string
  {
    if (is_array($value)) {
      return array_is_list($value)
        ? implode(', ', array_map($this->value(...), $value))
        : $this->object($value);
    }

    return match (true) {
      is_bool($value) => $value ? 'true' : 'false',
      $value === null => '',
      default => Markup::relinked((string) $value, $this->address->of(...)),
    };
  }

  /* A reference is its name over its address; a value object is its values, the
     way `Csv` flattens one, since neither has an address to link to. */
  private function object(array $object): string
  {
    if (isset($object['@id'])) {
      return $this->link($object);
    }
    unset($object['@type'], $object['position']);

    return implode(' ', array_map($this->value(...), $object));
  }

  /** @param array<string, mixed> $record */
  private function link(array $record): string
  {
    return sprintf('[%s](%s)', $this->heading($record),
      $this->address->of((string) ($record['@id'] ?? '')));
  }

  /** @param array<string, mixed> $document */
  private function heading(array $document): string
  {
    $heading = $document['headline'] ?? $document['name'] ?? $document['@id'] ?? '';

    return is_string($heading) ? $heading : (string) ($document['@id'] ?? '');
  }
}
