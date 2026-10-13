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

/* The seven editorial types. Each draws on the CreativeWork block, and
   each states its own † there rather than in the pool. */
final readonly class Editorial
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
      $this->work('Article', $this->article()),
      $this->work('WebPage', $this->webPage()),
      $this->work('WebSite', $this->webSite()),
      $this->work('HowTo', $this->howTo()),
      $this->work('Course', $this->course(), LocaleMode::Localise),
      $this->work('Dataset', $this->dataset(), LocaleMode::Both),
      $this->work('Book', $this->book()),
    ];
  }

  /** @param list<FieldDefinition> $own */
  private function work(string $name, array $own, LocaleMode $locale = LocaleMode::Fork): TypeDefinition
  {
    /* The words are the thing here, so a translation is a second record with
       its own slug and lifecycle — but for two. A course is its runs
       and their price range, which a second record would split, so it is labelled
       like an event, and the language it is taught in is `available_language`.
       A dataset is its data: it forks where the files differ, like a brochure,
       and is labelled where only its description does. */
    return new TypeDefinition($name, Erasure::Purge, $locale, Reach::Public, $own,
      [...$this->base->creativeWork($locale), ...$this->base->thing()]);
  }

  /** @return list<FieldDefinition> */
  private function article(): array
  {
    $f = $this->f;

    return [
      /* headline is the article's name, so name is not required beside it. */
      $f->text('name', 'Name')->words(),
      $f->text('headline', 'Headline')->required()->words(),
      $f->refs('author', 'Author', 'Person', 'Organization')->starter(),
      /* What a post is: its body and its date, on the first screen with the
         author and not offered afterwards. */
      $f->markdown('text', 'Body')->starter(),
      $f->date('date_published', 'Published')->starter(),
      $this->base->image()->starter(),
      $f->term('article_section', 'Section'),
      $f->longText('backstory', 'Backstory'),
      $f->number('page_start', 'First page'),
      $f->number('page_end', 'Last page'),
      $f->text('pagination', 'Pagination'),
    ];
  }

  /** @return list<FieldDefinition> */
  private function webPage(): array
  {
    $f = $this->f;

    return [
      $f->ref('breadcrumb', 'Breadcrumb', 'ItemList'),
      $f->date('last_reviewed', 'Last reviewed'),
      /* The name is the <title>, the headline the <h1>; a page has both. */
      $f->text('headline', 'Headline')->starter()->words(),
      $f->markdown('text', 'Body')->starter(),
      $this->base->image()->starter(),
      $f->term('specialty', 'Specialty'),
      /* How a page borrows another entity's content without duplicating it —
         an imprint is a view of an Organization, never a copy of one. */
      $f->ref('main_entity', 'Main entity', '*')->starter(),
      $f->image('primary_image_of_page', 'Lead image'),
      $f->ref('reviewed_by', 'Reviewed by', 'Person'),
      $f->link('related_link', 'Related link')->repeatable(),
      $f->link('significant_link', 'Significant link')->repeatable(),
      /* Narrower than the pool's: a page belongs to a site, and the list a
         work may sit in is not a page's. ‡, since the site holds its address. */
      $f->ref('is_part_of', 'Part of', 'WebSite')->starter(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function webSite(): array
  {
    $f = $this->f;

    return [
      $f->link('url', 'URL')->required(),
      /* No authored in_language: a site is a work, so it forks per locale and
         the inherited derived field answers from the spine. */
      $f->text('issn', 'ISSN'),
      $f->ref('publisher', 'Publisher', 'Person', 'Organization')->starter(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function howTo(): array
  {
    $f = $this->f;

    return [
      $f->objects('step', 'Steps', 'HowToStep')->required(),
      $f->objects('tool', 'Tools', 'HowToTool'),
      $f->objects('supply', 'Supplies', 'HowToSupply'),
      $f->duration('total_time', 'Total time')->starter(),
      $this->base->image()->starter(),
      $f->duration('prep_time', 'Preparation time'),
      $f->duration('perform_time', 'Time to perform'),
      /* An amount and its currency, never one string, or it cannot be sorted or
         summed. `currency` is MonetaryAmount's own key, carried onto the type by
         resolving the value object, so Claims asks MonetaryAmount about it and
         not HowTo. */
      $f->number('estimated_cost', 'Estimated cost')
        ->keyOf('MonetaryAmount', 'value', 'estimatedCost'),
      $f->listed('currency', 'Currency', ClosedList::Currency)->keyOf('MonetaryAmount', 'currency'),
      $f->number('yield', 'Yield')->keyOf('QuantitativeValue', 'value', 'yield'),
      $f->text('yield_unit', 'Yield: unit')->keyOf('QuantitativeValue', 'unitText', 'yield'),
    ];
  }

  /** @return list<FieldDefinition> */
  private function course(): array
  {
    $f = $this->f;

    return [
      $f->text('course_code', 'Course code')->starter(),
      $this->base->image()->starter(),
      $f->refs('course_prerequisites', 'Prerequisites', 'Course'),
      $f->number('number_of_credits', 'Credits'),
      $f->text('educational_credential_awarded', 'Credential awarded')->words(),
      $f->text('occupational_credential_awarded', 'Occupational credential')->words(),
      $f->term('educational_level', 'Level'),
      $f->term('teaches', 'Learning outcomes')->repeatable(),
      $f->refs('syllabus_sections', 'Syllabus', 'ItemList'),
      $f->listed('available_language', 'Available languages', ClosedList::Language)->repeatable(),
      $f->boolean('financial_aid_eligible', 'Eligible for financial aid'),
      $f->refs('has_course_instance', 'Scheduled runs', 'Event'),
      $f->ref('provider', 'Provider', 'Organization')->starter(),
      /* Authored Offers where a course sells directly; where it sells its dates,
         the emitted AggregateOffer is composed over hasCourseInstance. */
      $f->refs('offers', 'Offers', 'Offer')
        ->derived(Derivation::over(Derived::Range, 'has_course_instance')),
    ];
  }

  /** @return list<FieldDefinition> */
  private function dataset(): array
  {
    $f = $this->f;

    return [
      $f->objects('distribution', 'Distribution', 'DataDownload')->starter(),
      $f->objects('variable_measured', 'Variable measured', 'PropertyValue'),
      $f->text('measurement_technique', 'Measurement technique'),
      $f->text('temporal_coverage', 'Period covered'),
      $f->ref('spatial_coverage', 'Area covered', 'Place'),
      $f->ref('included_in_data_catalog', 'In catalogue', 'ItemList'),
      $f->refs('creator', 'Creator', 'Person', 'Organization')->starter(),
      $f->link('license', 'Licence')->starter(),
    ];
  }

  /* The one specialisation that cannot be an `additionalType`, there being no
     generic CreativeWork type to hang it on. The weakest keeper: if a slot is
     ever needed, look here first.
     @return list<FieldDefinition> */
  private function book(): array
  {
    $f = $this->f;

    return [
      $f->text('isbn', 'ISBN')->starter(),
      $f->text('book_edition', 'Edition'),
      $f->select('book_format', 'Format', ['Hardcover', 'Paperback', 'EBook', 'AudiobookFormat'])
        ->starter(),
      $f->number('number_of_pages', 'Pages'),
      $this->base->image()->starter(),
      $f->refs('illustrator', 'Illustrator', 'Person'),
      $f->refs('example_of_work', 'Edition of', 'Book'),
    ];
  }
}
