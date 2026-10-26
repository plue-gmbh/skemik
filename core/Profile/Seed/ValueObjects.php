<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\Cardinality;
use Profile\ClosedList;
use Profile\Derivation;
use Profile\Derived;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\ValueObject;

/* The value objects, their keys and the keys that
   bear them. A type table names one of these and nothing else — the keys live
   here alone, so a use that narrowed them in prose cannot decide which fields
   the product has. */
final readonly class ValueObjects
{
  /* The key, the English label and what each key of a flattened value object
     holds: a kind, the options of a select, the world's list, or `T` for a term
     of the house's own, with its set's code after it where that is not the
     key's own name — text where it says nothing — and after a kind `words` for
     what a reader reads or `whole` for a count. An empty label is the object's
     own value, named by its heading alone, as a width is. A repeatable value object
     needs none of it: it stays one field of kind `objects` and the editor draws
     its columns from the key set above. Kinds are every one but `objects`,
     because a key is an ordinary field. */
  private const FLATTENED = [
    'PostalAddress' => [
      'streetAddress' => ['street', 'Street'],
      'addressLocality' => ['locality', 'Town'],
      'addressRegion' => ['region', 'Region'],
      'postalCode' => ['postal_code', 'Postcode'],
      'addressCountry' => ['country', 'Country', ClosedList::Country],
      'postOfficeBoxNumber' => ['post_office_box', 'PO box'],
    ],
    'GeoCoordinates' => [
      'latitude' => ['latitude', 'Latitude', FieldKind::Number],
      'longitude' => ['longitude', 'Longitude', FieldKind::Number],
      'elevation' => ['elevation', 'Elevation', FieldKind::Number],
    ],
    'PeopleAudience' => [
      'audienceType' => ['type', '', FieldKind::Text, 'words'],
      'geographicArea' => ['area', 'Geographic area'],
      'suggestedMinAge' => ['min_age', 'Minimum age', FieldKind::Number],
      'suggestedMaxAge' => ['max_age', 'Maximum age', FieldKind::Number],
      'suggestedGender' => ['gender', 'Suggested gender', Cardinality::T, 'gender'],
    ],
    'AggregateRating' => [
      'ratingValue' => ['value', '', FieldKind::Number],
      'bestRating' => ['best', 'Best possible', FieldKind::Number],
      'worstRating' => ['worst', 'Worst possible', FieldKind::Number],
      'ratingCount' => ['count', 'Number of ratings', FieldKind::Number, 'whole'],
      'reviewCount' => ['reviews', 'Number of reviews', FieldKind::Number, 'whole'],
    ],
    'Rating' => [
      'ratingValue' => ['value', '', FieldKind::Number],
      'bestRating' => ['best', 'Best possible', FieldKind::Number],
      'worstRating' => ['worst', 'Worst possible', FieldKind::Number],
      'ratingExplanation' => ['explanation', 'Explanation', FieldKind::LongText],
    ],
    'MerchantReturnPolicy' => [
      'returnPolicyCategory' => ['category', '', ['MerchantReturnFiniteReturnWindow',
        'MerchantReturnUnlimitedWindow', 'MerchantReturnNotPermitted', 'MerchantReturnUnspecified']],
      'merchantReturnDays' => ['days', 'Days to return', FieldKind::Number, 'whole'],
      'returnMethod' => ['method', 'Return method', ['ReturnByMail', 'ReturnInStore', 'ReturnAtKiosk',
        'KeepProduct']],
      'returnFees' => ['fees', 'Return fees', ['FreeReturn', 'ReturnShippingFees',
        'ReturnFeesCustomerResponsibility', 'OriginalShippingFees', 'RestockingFees']],
    ],
    'UnitPriceSpecification' => [
      'unitCode' => ['unit_code', 'Unit'],
      'referenceQuantity' => ['reference_quantity', 'Per quantity', FieldKind::Number],
      'billingDuration' => ['billing_duration', 'Billing period', FieldKind::Duration],
      'billingIncrement' => ['billing_increment', 'Billing increment', FieldKind::Number],
      'valueAddedTaxIncluded' => ['vat_included', 'VAT included', FieldKind::Boolean],
    ],
    'OfferShippingDetails' => [
      'shippingRate' => ['rate', 'Rate', FieldKind::Number],
      'shippingDestination' => ['destination', 'Ships to'],
      'deliveryTime' => ['delivery_time', 'Delivery time'],
    ],
    'Ticket' => [
      'ticketNumber' => ['number', 'Number'],
      'ticketToken' => ['token', 'Token'],
      'ticketedSeat' => ['seat', 'Seat'],
      'totalPrice' => ['price', 'Price', FieldKind::Number],
    ],
    'EntryPoint' => [
      'urlTemplate' => ['url_template', 'URL template'],
      'encodingType' => ['encoding_type', 'Encoding type'],
      'contentType' => ['content_type', 'Content type'],
      'httpMethod' => ['http_method', 'HTTP method'],
    ],
    'Answer' => [
      'text' => ['text', 'Answer', FieldKind::Markdown],
      'author' => ['author', 'Answered by'],
      'url' => ['url', 'Answer address', FieldKind::Link],
      'upvoteCount' => ['upvotes', 'Upvotes', FieldKind::Number, 'whole'],
    ],
  ];

  /** @return list<ValueObject> */
  public function all(): array
  {
    return [
      new ValueObject('PostalAddress',
        ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry', 'postOfficeBoxNumber'],
        ['addressLocality']),
      new ValueObject('GeoCoordinates',
        ['latitude', 'longitude', 'elevation'],
        ['latitude', 'longitude']),
      new ValueObject('ContactPoint',
        ['contactType', 'telephone', 'email', 'areaServed', 'availableLanguage', 'contactOption', 'hoursAvailable'],
        ['contactType'], dialled: ['telephone'], kinds: ['email' => FieldKind::Link],
        options: ['contactOption' => ['TollFree', 'HearingImpairedSupported']]),
      new ValueObject('ServiceChannel',
        ['serviceUrl', 'servicePhone', 'serviceSmsNumber', 'serviceLocation', 'availableLanguage', 'processingTime'],
        ['serviceUrl'], dialled: ['servicePhone', 'serviceSmsNumber'],
        kinds: ['serviceUrl' => FieldKind::Link, 'processingTime' => FieldKind::Duration]),
      new ValueObject('OpeningHoursSpecification',
        ['dayOfWeek', 'opens', 'closes', 'validFrom', 'validThrough'],
        ['dayOfWeek', 'opens', 'closes'],
        kinds: ['opens' => FieldKind::Time, 'closes' => FieldKind::Time, 'validFrom' => FieldKind::Date,
          'validThrough' => FieldKind::Date],
        options: ['dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
          'PublicHolidays']]),
      new ValueObject('QuantitativeValue',
        ['value', 'unitCode', 'unitText', 'minValue', 'maxValue'],
        ['value'], amount: true),
      new ValueObject('MonetaryAmount',
        ['value', 'currency', 'minValue', 'maxValue', 'validFrom', 'validThrough'],
        ['value', 'currency'], amount: true),
      new ValueObject('UnitPriceSpecification',
        ['price', 'priceCurrency', 'unitCode', 'billingDuration', 'billingIncrement', 'referenceQuantity',
          'valueAddedTaxIncluded'],
        ['price', 'priceCurrency']),
      new ValueObject('PropertyValue',
        ['propertyID', 'name', 'value', 'unitCode', 'minValue', 'maxValue'],
        ['name', 'value'], kinds: ['minValue' => FieldKind::Number, 'maxValue' => FieldKind::Number]),
      /* Two classes, not one row with two names: the two counts are
         AggregateRating's and the vocabulary refuses them on a Rating. */
      /* No author of its own: a rating sits inside a Review that names one. */
      new ValueObject('Rating',
        ['ratingValue', 'bestRating', 'worstRating', 'ratingExplanation'],
        ['ratingValue']),
      new ValueObject('AggregateRating',
        ['ratingValue', 'bestRating', 'worstRating', 'ratingCount', 'reviewCount'],
        ['ratingValue']),
      new ValueObject('AggregateOffer',
        ['lowPrice', 'highPrice', 'priceCurrency', 'offerCount'],
        []),
      new ValueObject('Schedule',
        ['repeatFrequency', 'repeatCount', 'byDay', 'byMonth', 'byMonthDay', 'byMonthWeek', 'startDate', 'endDate',
          'startTime', 'endTime', 'exceptDate', 'scheduleTimezone'],
        ['repeatFrequency'], kinds: ['repeatCount' => FieldKind::Number, 'byMonth' => FieldKind::Number,
          'byMonthDay' => FieldKind::Number, 'byMonthWeek' => FieldKind::Number, 'startDate' => FieldKind::Date,
          'endDate' => FieldKind::Date, 'startTime' => FieldKind::Time, 'endTime' => FieldKind::Time,
          'exceptDate' => FieldKind::Date],
        whole: ['repeatCount', 'byMonth', 'byMonthDay', 'byMonthWeek']),
      /* The three age and gender keys are PeopleAudience's, so the profile
         declares that subclass, and every audience here is one. */
      new ValueObject('PeopleAudience',
        ['audienceType', 'geographicArea', 'suggestedMinAge', 'suggestedMaxAge', 'suggestedGender'],
        ['audienceType']),
      new ValueObject('MerchantReturnPolicy',
        ['returnPolicyCategory', 'merchantReturnDays', 'returnMethod', 'returnFees'],
        ['returnPolicyCategory']),
      new ValueObject('OfferShippingDetails',
        ['shippingRate', 'shippingDestination', 'deliveryTime'],
        ['shippingDestination']),
      /* A step's image is a file of the library or a link elsewhere, so the
         referring key keeps its link kind, and a word typed there is an address.
         Its text is markdown, as a body is: a step with a mark is rendered with it. */
      new ValueObject('HowToStep',
        ['name', 'text', 'image', 'url'],
        ['text'], reference: 'image', targets: ['ImageObject'],
        kinds: ['text' => FieldKind::Markdown, 'image' => FieldKind::Link, 'url' => FieldKind::Link]),
      new ValueObject('HowToTool',
        ['name', 'description', 'image', 'url', 'requiredQuantity'],
        ['name'], reference: 'image', targets: ['ImageObject'],
        kinds: ['image' => FieldKind::Link, 'url' => FieldKind::Link]),
      new ValueObject('HowToSupply',
        ['name', 'description', 'image', 'url', 'requiredQuantity'],
        ['name'], reference: 'image', targets: ['ImageObject'],
        kinds: ['image' => FieldKind::Link, 'url' => FieldKind::Link]),
      new ValueObject('Answer',
        ['text', 'author', 'url', 'upvoteCount'],
        ['text'], reference: 'author', targets: ['Person', 'Organization'],
        kinds: ['upvoteCount' => FieldKind::Number, 'url' => FieldKind::Link], whole: ['upvoteCount']),
      new ValueObject('OrderItem',
        ['orderQuantity', 'orderItemNumber', 'orderItemStatus', 'orderedItem'],
        ['orderedItem'], reference: 'orderedItem', targets: ['Product', 'Service'],
        kinds: ['orderQuantity' => FieldKind::Number],
        options: ['orderItemStatus' => ['OrderPaymentDue', 'OrderProcessing', 'OrderInTransit', 'OrderDelivered',
          'OrderCancelled', 'OrderReturned']]),
      new ValueObject('VirtualLocation',
        ['url'],
        ['url'], kinds: ['url' => FieldKind::Link]),
      new ValueObject('Clip',
        ['name', 'startOffset', 'endOffset', 'url'],
        ['startOffset', 'endOffset'], kinds: ['startOffset' => FieldKind::Number,
          'endOffset' => FieldKind::Number, 'url' => FieldKind::Link]),
      new ValueObject('DataDownload',
        ['contentUrl', 'encodingFormat', 'contentSize'],
        ['contentUrl'], kinds: ['contentUrl' => FieldKind::Link]),
      new ValueObject('Ticket',
        ['ticketNumber', 'ticketToken', 'ticketedSeat', 'totalPrice'],
        ['ticketNumber']),
      new ValueObject('LocationFeatureSpecification',
        ['name', 'value', 'hoursAvailable'],
        ['name']),
      new ValueObject('Certification',
        ['name', 'issuedBy', 'validIn', 'validFrom', 'expires', 'certificationIdentification', 'certificationStatus'],
        ['name'], reference: 'issuedBy', targets: ['Organization'],
        kinds: ['validFrom' => FieldKind::Date, 'expires' => FieldKind::Date],
        options: ['certificationStatus' => ['CertificationActive', 'CertificationInactive']]),
      new ValueObject('EntryPoint',
        ['urlTemplate', 'encodingType', 'contentType', 'httpMethod'],
        ['urlTemplate']),
      /* A button: words and a link, which schema.org lets `target` hold in place
         of an EntryPoint. It shares its name with the type of the same class and
         never meets it: a value object is looked up by `Profile::valueObject`, a
         type by `byType`, and a row leaves as `@type: Action` inside a document
         while `Ingest` reads a document's own `@type` alone. */
      new ValueObject('Action',
        ['name', 'target'],
        ['name', 'target'], kinds: ['target' => FieldKind::Link]),
    ];
  }

  /* One value object, flattened onto a type: one field per key, each declaring
     the value object it came from, which key it is, and the property it arrived
     on. The storage key is prefixed with that property and nothing reads
     meaning back out of the prefix — `shipping_details_rate` is the
     `shippingDetails` object because the field says so, not because it is spelt
     that way.
     @return list<FieldDefinition> */
  public function flatten(
    string $valueObject,
    string $property,
    string $label,
    Fields $f,
    bool $starter = false,
    bool $computed = false,
  ): array {
    $prefix = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
    $fields = [];
    foreach (self::FLATTENED[$valueObject] as $key => $spec) {
      $holds = $spec[2] ?? FieldKind::Text;
      $stored = $prefix . '_' . $spec[0];
      $named = $spec[1] === '' ? $label : $label . ': ' . $spec[1];
      $field = $holds === Cardinality::T
        ? $f->term($stored, $named, $spec[3] ?? null)->keyOf($valueObject, $key, $property)
        : $f->valueOf($valueObject, $key, $stored, $named, $holds, $property);
      /* ‡ binds the object, not its keys: an address puts its five fields on
         the first screen together or not at all. */
      $field = match ($holds === Cardinality::T ? null : $spec[3] ?? null) {
        'words' => $field->words(),
        'whole' => $field->whole(),
        default => $field,
      };
      $field = $starter ? $field->starter() : $field;
      $fields[] = $computed ? $field->derivable($this->computed($valueObject)[$key]) : $field;
    }

    return $fields;
  }

  /* The one row the type cannot answer, and what the answer needs to be worth
     choosing: a house whose reviews are rows here computes its rating over
     them, and one whose reviews live at a platform stores the number instead.
     `reviewCount` counts the reviews and `ratingCount` those carrying stars,
     since a review may be words alone and the average is over the stars.
     `bestRating` and `worstRating` are the scale rather than the data —
     one hop onto the reviews' own, so a computed 4.3 still says out of what.
     @return array<string, Derivation> by the value object's key */
  private function computed(string $valueObject): array
  {
    assert($valueObject === 'AggregateRating', 'the only value object a collection computes');

    return [
      'ratingValue' => Derivation::over(Derived::Avg, 'item_reviewed', 'review_rating_value')->inbound(),
      'bestRating' => Derivation::over(Derived::Max, 'item_reviewed', 'review_rating_best')->inbound(),
      'worstRating' => Derivation::over(Derived::Min, 'item_reviewed', 'review_rating_worst')->inbound(),
      'ratingCount' => Derivation::over(Derived::Count, 'item_reviewed', 'review_rating_value')->inbound(),
      'reviewCount' => Derivation::over(Derived::Count, 'item_reviewed')->inbound(),
    ];
  }
}
