<?php

declare(strict_types=1);

namespace Profile;

use Domain\Violation;

/* The half of `Claims` that asks what a field holds of what its property holds
   in the released vocabulary: an edge's targets, a value's data type, a value
   object's class and its keys, and an edge's words on the wrapper the document
   carries them in. Asked as the document leaves, so a `MediaObject` is every
   class `Seed\Media` emits it as and the property must take each of them. The
   profile states the same as `rangeIncludes`, which nothing checked before: an
   `image` field once pointed at any file and passed. A step outside a range is
   declared with its reason, as `Claims` declares one outside a domain, and a
   declared step the vocabulary has since caught up with is stale. */
final readonly class Ranges
{
  private const NO_WORK = 'an ItemList is no work, so a work holds its lists here and its parts name it by isPartOf';
  private const KIND = 'the target stays one of the thirty and says its kind in additionalType, which a page lifts'
    . ' into @type';
  private const OWN_TERMS = 'the house\'s own terms, where the vocabulary lists members nobody here has words for';
  private const REGION = 'a region is a term or a code a reader knows, where the vocabulary wants a record per area';
  private const COUNTRY = 'an ISO 3166 code, as addressCountry takes one, where a Country would be a record of its'
    . ' own';
  private const IN_A_ROW = 'words inside a row, which holds no rows of its own';

  /* Fields holding what the vocabulary does not list in their property's range,
     each with its reason, by `Class.property`. */
  private const OFF_RANGE = [
    'Article.hasPart' => self::NO_WORK,
    'WebPage.hasPart' => self::NO_WORK,
    'WebSite.hasPart' => self::NO_WORK,
    'HowTo.hasPart' => self::NO_WORK,
    'Course.hasPart' => self::NO_WORK,
    'Dataset.hasPart' => self::NO_WORK,
    'Book.hasPart' => self::NO_WORK,
    'SoftwareApplication.hasPart' => self::NO_WORK,
    'Organization.hasOfferCatalog' => self::KIND,
    'LocalBusiness.hasOfferCatalog' => self::KIND,
    'Service.hasOfferCatalog' => self::KIND,
    'WebPage.breadcrumb' => self::KIND,
    'Course.hasCourseInstance' => self::KIND,
    'Course.syllabusSections' => 'a course\'s sections are a list of its pages, and no type holds a Syllabus',
    'Dataset.includedInDataCatalog' => 'a catalogue is a list of datasets, and no type holds a DataCatalog',
    'Person.hasOccupation' => 'an occupation is the house\'s own term, an Occupation being a record of skills and'
      . ' pay nobody here keeps',
    'Organization.nonprofitStatus' => self::OWN_TERMS,
    'LocalBusiness.nonprofitStatus' => self::OWN_TERMS,
    'WebPage.specialty' => self::OWN_TERMS,
    'Product.hasAdultConsideration' => self::OWN_TERMS,
    'MediaObject.regionsAllowed' => self::REGION,
    'JobPosting.applicantLocationRequirements' => self::REGION,
    'PeopleAudience.geographicArea' => self::REGION,
    'Certification.validIn' => self::REGION,
    'OfferShippingDetails.shippingDestination' => self::REGION,
    'Person.nationality' => self::COUNTRY,
    'Product.countryOfOrigin' => self::COUNTRY,
    'DefinedTerm.inDefinedTermSet' => 'a set is the term at its root, since a DefinedTermSet would be a type of its'
      . ' own',
    'Person.seeks' => 'a demand is an offer turned round, and no type holds a Demand',
    'MemberProgram.hasTiers' => 'a tier is a price with conditions, and a price is an Offer here or it is nothing',
    'Offer.itemOffered' => 'a membership tier is an offer of its programme, so the programme is among what an offer'
      . ' offers',
    'UnitPriceSpecification.referenceQuantity' => 'a number of the price\'s own unit, where a QuantitativeValue'
      . ' would say the unit twice',
    'OfferShippingDetails.shippingRate' => 'an amount in the offer\'s currency, where a MonetaryAmount would say'
      . ' the currency twice',
    'OfferShippingDetails.deliveryTime' => 'a time in words, where a ShippingDeliveryTime is four quantities nobody'
      . ' fills',
    'Offer.warranty' => 'a WarrantyPromise, and the profile declares no such value object',
    'Order.orderDelivery' => 'a delivery in words until an order names an address to ship to',
    'Ticket.ticketedSeat' => 'a seat by its label, where a Seat is four keys of its own',
    'Action.error' => 'what went wrong in words, where the vocabulary takes any thing',
    'ContactPoint.hoursAvailable' => self::IN_A_ROW,
    'LocationFeatureSpecification.hoursAvailable' => self::IN_A_ROW,
    'ServiceChannel.servicePhone' => self::IN_A_ROW,
    'ServiceChannel.serviceSmsNumber' => self::IN_A_ROW,
    'ServiceChannel.serviceLocation' => self::IN_A_ROW,
  ];

  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private Vocabulary $vocabulary,
  ) {}

  /** @return array<string, list<Violation>> */
  public function checks(): array
  {
    return ['ranges' => $this->checkFields(), 'wrappers' => $this->checkWrappers()];
  }

  /** @return list<Violation> */
  private function checkFields(): array
  {
    $held = [];
    foreach ($this->profile->types() as $type) {
      foreach ($type->fields() as $field) {
        $held = array_merge_recursive($held, $this->held($field, $type));
      }
    }
    foreach ($this->rowed() as $object) {
      $held = array_merge_recursive($held, $this->rows($object));
    }

    return $this->judged($held);
  }

  /* A flattened field holds its key's value, and its value object is what the
     property it arrives on holds; a key both amounts carry is asked alone.
     @return array<string, list<string>> by `Class.property` */
  private function held(FieldDefinition $field, TypeDefinition $type): array
  {
    $name = (string) $this->schema->propertyName($field->key);
    $held = [$this->schema->domainOf($field, $type->name) . '.' . $name => $this->holds($field, $name)];
    if ($field->valueObjectOf !== null) {
      $held[($field->ofKind ?? $type->name) . '.' . $field->valueObjectOf] = [(string) $field->valueObject];
    }

    return $held;
  }

  /** @return list<string> */
  private function holds(FieldDefinition $field, string $property): array
  {
    $referring = $this->profile->referring($field);

    return match (true) {
      $field->cardinality === Cardinality::T => [$this->term($property)],
      $field->cardinality->isEdge() => $this->emitted($field->targets, $property),
      $field->kind === FieldKind::Objects => [(string) $field->ref],
      $referring !== null => $this->emitted($referring->targets),
      default => $this->schema->holds($field->kind, $field->options, $field->whole),
    };
  }

  /* A list carries a term as its code, a text, and a deeper document as the
     term itself: either is what the property holds where its range takes it. */
  private function term(string $property): string
  {
    $range = $this->vocabulary->rangeOf($property) ?? [];
    $text = !$this->schema->within($range, 'DefinedTerm') && $this->schema->within($range, 'Text');

    return $text ? 'Text' : 'DefinedTerm';
  }

    /* The value objects held as rows, whose keys no field declares.
     @return list<ValueObject> */
  private function rowed(): array
  {
    $fields = array_merge(...array_map(fn (TypeDefinition $type): array => $type->fields(), $this->profile->types()));
    $names = array_unique(array_filter(array_map(fn (FieldDefinition $field): ?string => $field->ref, $fields)));

    return array_values(array_filter(array_map($this->profile->valueObject(...), $names)));
  }

  /** @return array<string, list<string>> */
  private function rows(ValueObject $object): array
  {
    $held = [];
    foreach ($object->keys as $key) {
      $held[$object->name . '.' . $key] = $key === $object->reference ? $this->emitted($object->targets)
        : $this->schema->holds($object->kinds[$key] ?? FieldKind::Text, $object->options[$key] ?? [],
          in_array($key, $object->whole, true));
    }

    return $held;
  }

  /* A programme a membership names leaves inside its `ProgramMembership`.
     @param list<string> $targets @return list<string> */
  private function emitted(array $targets, string $property = ''): array
  {
    $classes = array_map(fn (string $target): array => match (true) {
      $target === '*' => ['Thing'],
      Qualifier::membership($property, $target) => ['ProgramMembership'],
      default => $this->schema->emittedAs($target),
    }, $targets);

    return array_merge(...$classes);
  }

  /* Each word an edge may carry, by the wrapper it is emitted in (`Feed\Wrappers`),
     is declared on that wrapper and holds what the property holds.
     @return list<Violation> */
  private function checkWrappers(): array
  {
    $held = [];
    foreach ($this->profile->types() as $type) {
      foreach ($type->edges() as $field) {
        $held = [...$held, ...$this->wrapped((string) $this->schema->propertyName($field->key), $field->targets)];
      }
    }
    $violations = [];
    foreach (array_keys($held) as $where) {
      [$wrapper, $name] = explode('.', $where, 2);
      if (!$this->schema->belongsTo($name, $wrapper)) {
        $violations[] = Violation::stated($where, 'the vocabulary does not declare this property on this type');
      }
    }

    return [...$violations, ...$this->judged($held)];
  }

  /** @param list<string> $targets @return array<string, list<string>> */
  private function wrapped(string $predicate, array $targets): array
  {
    $held = [];
    foreach ($targets as $target) {
      $wrapper = Qualifier::wrapper($predicate, $target);
      foreach (Qualifier::cases() as $qualifier) {
        $on = in_array($qualifier->value, Qualifier::ROLE, true) ? 'Role' : $wrapper;
        $held += $qualifier->heldOn($predicate, $target)
          ? [$on . '.' . $qualifier->value => [$qualifier->datatype()]] : [];
      }
    }

    return $held;
  }

  /* A property the vocabulary does not publish is `Claims`' to name, not this.
     @param array<string, list<string>> $held @return list<Violation> */
  private function judged(array $held): array
  {
    $violations = [];
    foreach ($held as $where => $classes) {
      $range = $this->vocabulary->rangeOf(explode('.', $where, 2)[1]) ?? [];
      $outside = array_values(array_filter(array_unique($classes),
        fn (string $class): bool => !$this->schema->within($range, $class)));
      $declared = isset(self::OFF_RANGE[$where]);
      if ($range !== [] && $outside !== [] && !$declared) {
        $violations[] = Violation::stated($where, sprintf('holds %s where the vocabulary gives it %s',
          implode(' or ', $outside), implode(', ', $range)));
      }
      if ($range !== [] && $outside === [] && $declared) {
        $violations[] = Violation::stated($where, 'declared outside its range, and the vocabulary now takes it');
      }
    }

    return $violations;
  }
}
