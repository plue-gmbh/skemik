<?php

declare(strict_types=1);

namespace Profile;

/* Turns schema.org's released JSON-LD into `vocabulary.json`, the snapshot
   Vocabulary reads, and is how that file is made: run by hand before a release,
   never called by an install. No I/O: it is handed the decoded graph and
   answers the array to be written, so the refresh is a deliberate act and never
   something an install does to itself.
   The result is not pruned to what the profile names. A snapshot cut to the
   profile that produced it cannot tell a property the profile newly adds from
   a name nobody published — both are absent — which is how this check once
   called `currency` invented. */
final readonly class VocabularyDistiller
{
  public const SOURCE = 'https://schema.org/version/latest/schemaorg-current-https.jsonld';

  private const NOTE = 'Every class the released vocabulary publishes with its parents, every '
    . 'property with its domainIncludes and its rangeIncludes, every enumeration member with its '
    . 'enumerations, and every name superseded with its successor. Not pruned to the profile: a snapshot '
    . 'cut to what the profile already names cannot tell a property the profile newly adds from a name nobody '
    . 'published. Committed, refreshed deliberately, never fetched at runtime.';

  /**
   * @param array<string, mixed> $graph the decoded JSON-LD, `@graph` and all
   * @return array<string, mixed> ready for json_encode into vocabulary.json
   */
  public function distil(array $graph, string $today): array
  {
    $nodes = $this->schemaNodes($graph);

    return ['source' => self::SOURCE, 'distilled' => $today, 'note' => self::NOTE,
      'superseded' => $this->superseded($nodes), 'classes' => $this->classes($nodes),
      'properties' => $this->properties($nodes, 'schema:domainIncludes'),
      'ranges' => $this->properties($nodes, 'schema:rangeIncludes'), 'members' => $this->members($nodes)];
  }

  /* The released file carries the vocabularies schema.org aligns with —
     `dcat:Dataset`, `skos:broader` — under the same graph. Keying on the local
     name alone lets dcat:Dataset overwrite schema:Dataset.
     @param array<string, mixed> $graph
     @return array<string, array<string, mixed>> schema.org's own nodes, by local name */
  private function schemaNodes(array $graph): array
  {
    $nodes = [];
    foreach ($graph['@graph'] ?? [] as $node) {
      $id = (string) ($node['@id'] ?? '');
      if (str_starts_with($id, 'schema:')) {
        $nodes[$this->localName($id)] = $node;
      }
    }
    ksort($nodes);

    return $nodes;
  }

  /* Kept because domains cannot show it: `branchOf` is still declared on
     LocalBusiness, and only this says `parentOrganization` took its place.
     @return array<string, string> each superseded name with its first successor */
  private function superseded(array $nodes): array
  {
    $by = array_map(fn (array $node): array => $this->names($node['schema:supersededBy'] ?? null), $nodes);

    return array_map(fn (array $names): string => $names[0], array_filter($by));
  }

  /** @return array<string, list<string>> each class with its parents */
  private function classes(array $nodes): array
  {
    $classes = array_filter($nodes, fn (array $node): bool => ($node['@type'] ?? null) === 'rdfs:Class');

    return array_map(fn (array $node): array => $this->names($node['rdfs:subClassOf'] ?? null), $classes);
  }

  /** @return array<string, list<string>> each property with the types it is declared on, or holds */
  private function properties(array $nodes, string $key): array
  {
    $isProperty = fn (array $node): bool => ($node['@type'] ?? null) !== 'rdfs:Class'
      && str_contains($this->typeOf($node), 'rdf:Property');

    return array_map(
      fn (array $node): array => $this->names($node[$key] ?? null),
      array_filter($nodes, $isProperty),
    );
  }

  /* Kept because a select stores a member by its name, `EventScheduled`, and
     only its type says it is an `EventStatusType`.
     @return array<string, list<string>> each member with the enumerations it is typed by */
  private function members(array $nodes): array
  {
    $isMember = fn (array $node): bool => !str_contains($this->typeOf($node), 'rdfs:Class')
      && !str_contains($this->typeOf($node), 'rdf:Property');
    $types = fn (array $node): array => array_values(array_map($this->localName(...),
      array_filter((array) $node['@type'], fn (string $type): bool => str_starts_with($type, 'schema:'))));

    return array_filter(array_map($types, array_filter($nodes, $isMember)));
  }

  /* `domainIncludes`, `rangeIncludes` and `subClassOf` are one node, a list, or absent.
     @return list<string> */
  private function names(mixed $value): array
  {
    if ($value === null) {
      return [];
    }
    $nodes = array_is_list($value) ? $value : [$value];

    return array_values(array_map(fn (array $n): string => $this->localName((string) $n['@id']), $nodes));
  }

  private function localName(string $id): string
  {
    $parts = explode(':', $id);

    return end($parts);
  }

  /* A node's `@type` is a string or a list of them. */
  private function typeOf(array $node): string
  {
    $type = $node['@type'] ?? '';

    return is_array($type) ? implode(' ', $type) : (string) $type;
  }
}
