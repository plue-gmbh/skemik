<?php

declare(strict_types=1);

namespace Storage;

use Domain\Exceptions\MalformedValue;

/* One saved question of the home screen: a collection by its slug, the query
   bar's narrowing as the address spells it, relative dates kept relative so the
   question keeps its month, and one figure over all it narrows to. It holds no
   name: its title is said from these in each reader's language. */
final readonly class Question
{
  /* What orders, pages or draws the collection and narrows nothing. */
  private const UNSAVED = ['sort', 'page', 'per_page', 'view', 'in', 'words'];

  public string $collection;

  /** @param ?string $field the number field's key a sum or a latest value reads, none for a count */
  public function __construct(
    public string $id,
    string $collection,
    public string $query,
    public Figure $figure,
    public ?string $field = null,
  ) {
    if (!in_array($collection, Collection::SLUGS, true)) {
      throw MalformedValue::named('collection of a saved question', $collection);
    }
    /** @psalm-taint-escape input */
    $this->collection = $collection; /* one of the release's slugs, which go into a path and a redirect */
  }

  /** An address's parameters as a question keeps them, in one order, each one's values too, since the query
      bar joins them as they are chosen, so two spellings of one narrowing are one. A search stays as typed,
      its comma being no list's.
      @param array<string, string> $parameters */
  public static function narrowing(array $parameters): string
  {
    $kept = array_diff_key($parameters, array_flip(self::UNSAVED));
    ksort($kept);
    foreach (array_diff_key($kept, ['q' => true]) as $name => $values) {
      $listed = explode(',', $values);
      sort($listed);
      $kept[$name] = implode(',', $listed);
    }

    return http_build_query($kept);
  }

  /** One saved before values were ordered is read in that order here, so its screen still knows it.
      @param array<string, string> $parameters the address a collection's screen was asked at */
  public function asks(string $collection, array $parameters): bool
  {
    parse_str($this->query, $saved);

    return $this->collection === $collection
      && self::narrowing(array_filter($saved, is_string(...))) === self::narrowing($parameters);
  }
}
