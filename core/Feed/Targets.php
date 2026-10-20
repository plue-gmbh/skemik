<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Relation;
use Domain\Slug;
use Profile\Cardinality;
use Profile\FieldDefinition;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\Seed\Media;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RelationRepository;

/* What a caller means by the name a document gave a target: a record of this
   house by its address, a term by its code. A filter on an edge and a write
   naming one both ask here, and the admin's query bar with them, so all are
   answered as the feed spells it. Whether the caller reads what it named is the
   asker's to judge, and a filter and a write judge alike: only what a document
   of the caller's reading shows is found. */
final readonly class Targets
{
  public function __construct(
    private EntityRepository $entities,
    private RelationRepository $relations,
    private CollectionStore $collections,
    private Profile $profile,
    private SchemaOrg $schema,
    private string $origin,
  ) {}

  /* An edge is asked by what a document says of its target: its address, or a
     term's code, in the feed and in the admin's query bar alike. A target the
     caller does not read names nothing, as it is nothing in a document, or a
     guessed slug would confirm an edge to a draft. What is no address at all, a
     bare slug or an id, is refused alike for every value, so the refusal
     confirms nothing and still names the mistake. An address is read against each
     locale in turn: the feed's route, or the house's locales on the admin's path,
     which names none.
     @param list<Locale> $locales
     @param \Closure(Entity): bool $reads whether the caller reads a target */
  public function filtered(Query $query, array $locales, \Closure $reads): Query
  {
    $type = $this->profile->byType($query->type);
    $filters = $query->filters;
    foreach ($filters as $key => $values) {
      $field = $type->field($key);
      if ($field?->cardinality->isEdge() === true) {
        $filters[$key] = array_merge(...array_map(fn (string $value): array => $this->ids(
          array_filter($this->named($field, $value, $locales), $reads)), $values));
      }
    }

    return $query->filteredBy($filters);
  }

  /* A document names a work in the reader's language wherever the edge holds another of its
     translations, so a filter naming it finds an edge to any of them, as the document shows it.
     @param array<Entity> $targets
     @return list<string> */
  private function ids(array $targets): array
  {
    $ids = [];
    foreach ($targets as $target) {
      $others = $this->profile->byType($target->type)->locale->forks()
        ? $this->entities->group($target)->others($target) : [];
      $ids = [...$ids, ...array_map(fn (Entity $one): string => $one->id->value, [$target, ...$others])];
    }

    return $ids;
  }

  /** @param list<Locale> $locales
      @return list<Entity> */
  private function named(FieldDefinition $field, string $value, array $locales): array
  {
    if ($field->cardinality === Cardinality::T) {
      return $this->coded($value);
    }
    if (preg_match('#^(https?://.+|[^/]+/[^/]+)$#i', $value) !== 1) {
      $name = (string) $this->schema->parameterName($field->key);
      throw MalformedValue::named('collection/slug address in ' . $name, $value);
    }
    foreach ($locales as $locale) {
      $record = $this->at($value, $locale);
      if ($record !== null) {
        return [$record];
      }
    }

    return [];
  }

  /* A set is a root term, one with no set of its own, found by its code. */
  public function root(string $set): ?Entity
  {
    return array_find($this->coded($set), fn (Entity $term): bool => $this->setOf($term) === null);
  }

  /* The one term of that code in that set, or none: a code is held once per set. */
  public function term(string $set, string $code): ?Entity
  {
    return array_find($this->coded($code), fn (Entity $term): bool => $this->setOf($term) === $set);
  }

  /* The term a typed word means, in any spelling of its case: `Kettlebell`
     beside `kettlebell` is one word twice, and the picker offers the held one. */
  public function worded(string $set, string $word): ?Entity
  {
    $query = new Query('DefinedTerm', ['term_code' => [$word]], perPage: Query::MAX_PER_PAGE);
    $spelt = mb_strtolower($word);

    return array_find($this->entities->list($query), fn (Entity $term): bool
      => mb_strtolower((string) $term->props->string('term_code')) === $spelt && $this->setOf($term) === $set);
  }

  /* What a field takes past its targets' type that the record is not, null where
     it fits: a term field its own set's terms, and a file field the files that
     leave as a class it names, since a PDF behind `image` is a MediaObject where
     the field says ImageObject. */
  public function misfit(FieldDefinition $field, Entity $record): ?string
  {
    $set = $field->cardinality === Cardinality::T ? $this->schema->termSet($field) : null;
    $class = $record->typeName() === 'MediaObject' ? Media::emittedType($record) : null;

    return match (true) {
      $set !== null && $this->setOf($record) !== $set => 'term in the set ' . $set,
      $class !== null && !$this->schema->within($field->targets, $class)
        => 'file of the class ' . implode(' or ', $field->targets),
      default => null,
    };
  }

  /* The code of the set a term is in, null for a root. */
  public function setOf(Entity $term): ?string
  {
    $edge = array_find($this->relations->outbound($term->id),
      fn (Relation $edge): bool => $edge->predicate === 'inDefinedTermSet');
    $root = $edge === null ? null : $this->entities->find($edge->object);

    return $root?->props->string('term_code');
  }

  /* Html, Csv and Markdown write an address from the collection on, so one is
     read against the locale route it was asked on. Null for an address of
     another house, of no record, or of no shape this house serves. */
  public function at(string $address, ?Locale $route = null): ?Entity
  {
    $prefix = $this->origin . '/feed/';
    $whole = $route === null || str_contains($address, '://') ? $address
      : $prefix . $route->tag . '/' . $address;
    $segments = explode('/', substr($whole, strlen($prefix)));
    if (!str_starts_with($whole, $prefix) || count($segments) !== 3) {
      return null;
    }
    try {
      $held = $this->collections->bySlug(new Slug($segments[1]));

      return $held === null ? null
        : $this->entities->bySlug($held->type, new Locale($segments[0]), new Slug($segments[2]));
    } catch (MalformedValue) {
      return null;
    }
  }

  /* The filter beneath compares text as a prefix, so `EU` would find `EUR`.
     @return list<Entity> every term of exactly that code */
  public function coded(string $code): array
  {
    $query = new Query('DefinedTerm', ['term_code' => [$code]], perPage: Query::MAX_PER_PAGE);

    return array_values(array_filter($this->entities->list($query),
      fn (Entity $term): bool => $term->props->string('term_code') === $code));
  }
}
