<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\ClosedList;
use Profile\Erasure;
use Profile\FieldDefinition;
use Profile\LocaleMode;
use Profile\Reach;
use Profile\TypeDefinition;

/* The four agents: who acts, who is dealt with, and where they are. */
final readonly class Agents
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
      new TypeDefinition('Person', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->person(), $this->base->thing(), nameParts: ['given_name', 'family_name']),
      new TypeDefinition('Organization', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->organization(), $this->base->thing()),
      /* Extends Organization + Place: its own rows first, then both parents'. */
      new TypeDefinition('LocalBusiness', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        [...$this->localBusiness(), ...$this->organization(), ...$this->place()],
        $this->base->thing()),
      new TypeDefinition('Place', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->place(), $this->base->thing()),
    ];
  }

  /** @return list<FieldDefinition> */
  private function person(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->text('given_name', 'Given name')->words(),
      $f->text('family_name', 'Family name')->words(),
      $f->text('additional_name', 'Middle name')->words(),
      $f->text('honorific_prefix', 'Honorific prefix')->words(),
      $f->text('honorific_suffix', 'Honorific suffix')->words(),
      $f->text('job_title', 'Job title')->repeatable()->starter()->words(),
      $f->link('email', 'Email')->repeatable()->starter(),
      $f->text('telephone', 'Telephone')->dialled()->repeatable()->starter(),
      $this->base->image()->starter(),
      ...$vo->flatten('PostalAddress', 'address', 'Address', $f),
      $f->date('birth_date', 'Date of birth'),
      $f->date('death_date', 'Date of death'),
      /* A term and not schema.org's two members: a German register holds
         *divers*, and the list is the house's to own. */
      $f->term('gender', 'Gender'),
      $f->listed('nationality', 'Nationality', ClosedList::Country)->repeatable(),
      $f->listed('knows_language', 'Languages', ClosedList::Language)->repeatable(),
      $f->term('knows_about', 'Areas of knowledge')->repeatable(),
      $f->text('award', 'Award')->repeatable()->words(),
      $f->objects('contact_point', 'Contact point', 'ContactPoint'),
      $f->term('has_occupation', 'Occupation', 'occupation')->repeatable(),
      $f->refs('works_for', 'Works for', 'Organization'),
      $f->refs('affiliation', 'Affiliation', 'Organization'),
      $f->refs('member_of', 'Member of', 'Organization', 'MemberProgram'),
      $f->refs('alumni_of', 'Alumnus of', 'Organization'),
      $f->ref('work_location', 'Work location', 'Place'),
      $f->refs('knows', 'Knows', 'Person'),
      $f->refs('brand', 'Brands', 'Brand'),
      $f->refs('owns', 'Owns', 'Product', 'Offer'),
      $f->refs('seeks', 'Seeks', 'Product', 'Offer'),
    ];
  }

  /** @return list<FieldDefinition> */
  private function organization(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->text('legal_name', 'Legal name')->starter()->words(),
      $f->image('logo', 'Logo')->starter(),
      $this->base->image()->starter(),
      $f->text('slogan', 'Slogan')->words(),
      $f->link('email', 'Email')->starter(),
      /* Its own website, which lives on another domain than anything held here. */
      $f->link('url', 'URL')->starter(),
      $f->text('telephone', 'Telephone')->dialled()->starter(),
      $f->text('fax_number', 'Fax number')->dialled(),
      ...$vo->flatten('PostalAddress', 'address', 'Address', $f, starter: true),
      $f->date('founding_date', 'Founded'),
      $f->date('dissolution_date', 'Dissolved'),
      $f->ref('founding_location', 'Founding location', 'Place'),
      /* Typed, never count(worksFor): three Person rows for a company of forty
         is a false public claim nobody typed. */
      $f->number('number_of_employees', 'Employees')
        ->keyOf('QuantitativeValue', 'value', 'numberOfEmployees'),
      $f->text('tax_id', 'Tax number'),
      $f->text('vat_id', 'VAT ID'),
      $f->text('lei_code', 'LEI code'),
      $f->text('global_location_number', 'Global location number'),
      $f->text('iso6523_code', 'ISO 6523 code'),
      $f->term('nonprofit_status', 'Nonprofit status'),
      $f->term('keywords', 'Keywords')->repeatable(),
      $f->term('knows_about', 'Areas of knowledge')->repeatable(),
      $f->refs('area_served', 'Area served', 'Place'),
      $f->objects('contact_point', 'Contact point', 'ContactPoint'),
      $f->refs('founder', 'Founder', 'Person'),
      /* A relation is held at the child's end only — `memberOf`, `worksFor`,
         `parentOrganization`, `containedInPlace` — because the inverse is the same
         fact typed a second time, and nothing would keep the two agreeing. */
      $f->refs('member_of', 'Member of', 'Organization', 'MemberProgram'),
      $f->objects('has_certification', 'Certification', 'Certification'),
      $f->ref('parent_organization', 'Parent organization', 'Organization'),
      $f->refs('location', 'Location', 'Place', 'LocalBusiness'),
      $f->refs('brand', 'Brands', 'Brand')->starter(),
      $f->ref('has_offer_catalog', 'Offer catalogue', 'ItemList'),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
      /* Off-domain on purpose: the escape rung the profile's
         most common type had none of. The proof link is absent by design. */
      $f->objects('additional_property', 'Additional property', 'PropertyValue')->words(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function localBusiness(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->objects('opening_hours_specification', 'Opening hours', 'OpeningHoursSpecification'),
      $f->objects('special_opening_hours_specification', 'Holiday hours', 'OpeningHoursSpecification'),
      $f->text('price_range', 'Price range'),
      $f->listed('currencies_accepted', 'Currencies accepted', ClosedList::Currency)->repeatable(),
      $f->term('payment_accepted', 'Payment accepted', 'paymentMethod')->repeatable(),
      ...$vo->flatten('GeoCoordinates', 'geo', 'Coordinates', $f),
    ];
  }

  /** @return list<FieldDefinition> */
  private function place(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      ...$vo->flatten('PostalAddress', 'address', 'Address', $f, starter: true),
      ...$vo->flatten('GeoCoordinates', 'geo', 'Coordinates', $f),
      $f->text('telephone', 'Telephone')->dialled()->starter(),
      $f->link('has_map', 'Map'),
      $f->number('maximum_attendee_capacity', 'Capacity'),
      $f->boolean('public_access', 'Open to the public'),
      $f->boolean('is_accessible_for_free', 'Free of charge'),
      $f->boolean('smoking_allowed', 'Smoking allowed'),
      $f->objects('amenity_feature', 'Amenity', 'LocationFeatureSpecification')->words(),
      $f->objects('additional_property', 'Additional property', 'PropertyValue')->words(),
      $f->text('branch_code', 'Branch code'),
      $this->base->image()->starter(),
      $f->ref('contained_in_place', 'Contained in', 'Place'),
      $f->objects('opening_hours_specification', 'Opening hours', 'OpeningHoursSpecification'),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
    ];
  }
}
