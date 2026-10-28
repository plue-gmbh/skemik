<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\Derivation;
use Profile\Derived;
use Profile\ClosedList;
use Profile\Erasure;
use Profile\FieldDefinition;
use Profile\LocaleMode;
use Profile\Reach;
use Profile\TypeDefinition;

/* What is sold — the thing, the service, and the mark they carry. The
   price is never here: it is an Offer row, which is what makes
   multi-currency, multi-region and tiered plans work without new tables. */
final readonly class Commerce
{
  public function __construct(
    private Base $base = new Base(),
    private Fields $f = new Fields(),
    private ValueObjects $vo = new ValueObjects(),
  ) {}

  /** @return list<TypeDefinition> */
  public function types(): array
  {
    return [
      new TypeDefinition('Product', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->product(), $this->base->thing()),
      new TypeDefinition('Service', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->service(), $this->base->thing()),
      new TypeDefinition('MemberProgram', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->memberProgram(), $this->base->thing()),
      new TypeDefinition('Brand', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->brand(), $this->base->thing()),
    ];
  }

  /** @return list<FieldDefinition> */
  private function product(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->text('sku', 'SKU')->starter(),
      $this->base->image()->starter(),
      $f->text('mpn', 'MPN'),
      $f->text('gtin', 'GTIN'),
      $f->text('product_id', 'Product ID'),
      $f->text('nsn', 'NSN'),
      $f->text('model', 'Model'),
      $f->term('color', 'Colour'),
      $f->term('material', 'Material'),
      $f->term('pattern', 'Pattern'),
      $f->term('size', 'Size'),
      /* An amount is its bearing key, plus the unit where a house can vary
         it — a shipping weight in kg or lb is not the same number. */
      $f->number('width', 'Width')->keyOf('QuantitativeValue', 'value', 'width'),
      $f->text('width_unit', 'Width: unit')->keyOf('QuantitativeValue', 'unitCode', 'width'),
      $f->number('height', 'Height')->keyOf('QuantitativeValue', 'value', 'height'),
      $f->text('height_unit', 'Height: unit')->keyOf('QuantitativeValue', 'unitCode', 'height'),
      $f->number('depth', 'Depth')->keyOf('QuantitativeValue', 'value', 'depth'),
      $f->text('depth_unit', 'Depth: unit')->keyOf('QuantitativeValue', 'unitCode', 'depth'),
      $f->number('weight', 'Weight')->keyOf('QuantitativeValue', 'value', 'weight'),
      $f->text('weight_unit', 'Weight: unit')->keyOf('QuantitativeValue', 'unitCode', 'weight'),
      $f->select('item_condition', 'Condition',
        ['NewCondition', 'UsedCondition', 'RefurbishedCondition', 'DamagedCondition']),
      $f->date('production_date', 'Produced'),
      $f->date('release_date', 'Released'),
      $f->date('purchase_date', 'Purchased'),
      $f->listed('country_of_origin', 'Country of origin', ClosedList::Country),
      $f->term('has_adult_consideration', 'Adult content'),
      $f->text('in_product_group_with_id', 'Product group ID'),
      /* The group is a product marked as one, and it names its variants: the
         inverse, `isVariantOf`, would hold the same fact a second time, and this
         end keeps S, M and L in their order. */
      $f->text('product_group_id', 'Group: ID')->ofKind('ProductGroup'),
      $f->text('varies_by', 'Group: varies by')->repeatable()->ofKind('ProductGroup'),
      $f->refs('has_variant', 'Group: variants', 'Product')->ofKind('ProductGroup'),
      $f->objects('additional_property', 'Specification', 'PropertyValue')->words(),   // spec sheets go here
      $f->text('award', 'Award')->repeatable()->words(),
      $f->text('slogan', 'Slogan')->words(),
      $f->term('category', 'Category'),
      $f->term('keywords', 'Keywords')->repeatable(),
      $f->ref('brand', 'Brand', 'Brand')->starter(),
      $f->ref('manufacturer', 'Manufacturer', 'Organization'),
      /* Never typed: an offer names what it offers, and the item states the range
         of the offers that do. */
      $f->refs('offers', 'Offers', 'Offer')->starter()
        ->derived(Derivation::over(Derived::Range, 'item_offered')->inbound()),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
      $f->refs('is_accessory_or_spare_part_for', 'Accessory or spare part for', 'Product'),
      $f->refs('is_consumable_for', 'Consumable for', 'Product'),
      $f->refs('is_related_to', 'Related to', 'Product'),
      $f->refs('is_similar_to', 'Similar to', 'Product'),
      ...$vo->flatten('MerchantReturnPolicy', 'hasMerchantReturnPolicy', 'Return policy', $f),
      ...$vo->flatten('PeopleAudience', 'audience', 'Audience', $f),
    ];
  }

  /** @return list<FieldDefinition> */
  private function service(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->term('service_type', 'Service type')->starter(),
      $this->base->image()->starter(),
      $f->ref('service_output', 'Output', '*'),
      $f->link('terms_of_service', 'Terms of service'),
      $f->select('provider_mobility', 'Provider mobility', ['static', 'dynamic']),
      $f->text('slogan', 'Slogan')->words(),
      $f->term('category', 'Category'),
      $f->ref('provider', 'Provider', 'Organization')->starter(),
      $f->refs('area_served', 'Area served', 'Place'),
      /* A channel answers in a language, so a second language may be a second address. */
      $f->objects('available_channel', 'Channel', 'ServiceChannel')->words(),
      $f->objects('hours_available', 'Hours available', 'OpeningHoursSpecification'),
      ...$vo->flatten('PeopleAudience', 'audience', 'Audience', $f),
      $f->refs('offers', 'Offers', 'Offer')
        ->derived(Derivation::over(Derived::Range, 'item_offered')->inbound()),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
      /* How a service says what it comprises — hasPart is CreativeWork's and
         this type is Intangible. */
      $f->ref('has_offer_catalog', 'Offer catalogue', 'ItemList'),
      $f->ref('brand', 'Brand', 'Brand'),
      $f->refs('is_related_to', 'Related to', 'Service', 'Product'),
      $f->refs('is_similar_to', 'Similar to', 'Service', 'Product'),
    ];
  }

  /** @return list<FieldDefinition> */
  private function memberProgram(): array
  {
    $f = $this->f;

    return [
      $f->ref('hosting_organization', 'Run by', 'Organization')->starter(),
      $f->refs('has_tiers', 'Tiers', 'Offer'),
      $this->base->description()->starter(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function brand(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->text('slogan', 'Slogan')->starter()->words(),
      $f->image('logo', 'Logo')->starter(),
      $this->base->image()->starter(),
      /* The brand tokens. Four names are ours — color-primary, color-ink,
         font-body, font-display — and a house adds its own, color-headline,
         since a name we made up is no vocabulary to close. The
         step off-domain costs the proof link and nothing else. A font token's
         value is the typeface's name and free text: a fact about the brand that
         a frontend does with what it wants, matched against no file here. */
      $f->objects('additional_property', 'Brand attribute', 'PropertyValue')->keys('name', 'value')->starter(),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
      $this->base->description(),
    ];
  }
}
