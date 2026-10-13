<?php

declare(strict_types=1);

namespace Storage;

use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\UnknownType;
use Domain\Slug;

/* One `collection` row: which of its type's fields a house carries, in order,
   and nothing else — every house holds one collection per type, given by the
   release and never made, named or ordered by a house. So the address is this
   table's and the same in every install, and the name is the chrome's word for the type, which `CollectionStore` reads
   from `language/`. `type` is the schema.org URL, as on `entity`. */
final readonly class Collection
{
  public const SLUGS = [
    'Person' => 'people', 'Organization' => 'organizations', 'LocalBusiness' => 'local-businesses',
    'Place' => 'places', 'Article' => 'articles', 'WebPage' => 'web-pages', 'WebSite' => 'web-sites',
    'HowTo' => 'how-tos', 'Course' => 'courses', 'Dataset' => 'datasets', 'Book' => 'books',
    'MediaObject' => 'media', 'Review' => 'reviews', 'Comment' => 'comments', 'Message' => 'messages',
    'Product' => 'products', 'Service' => 'services', 'Offer' => 'offers', 'Order' => 'orders',
    'Invoice' => 'invoices', 'MemberProgram' => 'membership-programs', 'Brand' => 'brands',
    'Event' => 'events', 'Reservation' => 'reservations', 'ItemList' => 'item-lists',
    'DefinedTerm' => 'defined-terms', 'JobPosting' => 'job-postings', 'SoftwareApplication' => 'software',
    'Question' => 'questions', 'Action' => 'actions',
  ];

  public Slug $slug;

  /** @param list<CollectionField> $fields in the order the screens draw them */
  public function __construct(
    public string $id,
    public string $type,
    public string $name,
    public array $fields,
  ) {
    $this->slug = new Slug(self::SLUGS[basename($type)] ?? throw UnknownType::named($type));
    $keys = array_map(fn (CollectionField $field): string => $field->key, $fields);
    if (count(array_unique($keys)) !== count($keys)) {
      throw MalformedValue::named('field list naming each key once', implode(', ', $keys));
    }
  }

  /** @param list<CollectionField> $fields */
  public function carrying(array $fields): self
  {
    return new self($this->id, $this->type, $this->name, $fields);
  }
}
