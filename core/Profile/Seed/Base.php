<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\Derivation;
use Profile\FieldDefinition;
use Profile\LocaleMode;

/* The Thing block, carried by all thirty, and the CreativeWork block, drawn
   on by the types whose own table says so.
   No FieldKind is an object: a single-valued value object resolves into
   fields of the other kinds, each declaring which key of which object it carries,
   and a repeatable one is the `objects` kind. */
final readonly class Base
{
  public function __construct(
    private Fields $f = new Fields(),
    private ValueObjects $vo = new ValueObjects(),
  ) {}

  /** @return list<FieldDefinition> */
  public function thing(): array
  {
    $f = $this->f;

    return [
      $f->text('name', 'Name')->required()->words(),
      $f->text('alternate_name', 'Alternative name')->repeatable()->words(),
      $this->description()->starter(),
      $f->longText('disambiguating_description', 'Disambiguating description'),
      $f->link('url', 'URL'),
      /* Rows, as a brand's tokens are: a register entry is a name and a value,
         and one string had the name typed into the value, the page showing the
         field's word above it. An invoice's number stays one string, its own row. */
      $f->objects('identifier', 'Identifier', 'PropertyValue')->keys('name', 'value'),
      $this->image(),
      /* What lets a term stand for a thing outside every type here, an element
         or a legal form: its identity is Wikidata's or a register's, not ours. */
      $f->link('same_as', 'Same as')->repeatable(),
      $f->text('additional_type', 'Specialization')->repeatable(),
      $f->ref('main_entity_of_page', 'Page about this', 'WebPage'),
      /* Thing's, as schema.org has it: an offer's *Book now* and an event's
         *Sign up* are buttons as much as a page's. */
      $f->objects('potential_action', 'Call to action', 'Action')->words(),
    ];
  }

  /* Without ‡, for the own table of a type that starts without it: a brand, a
     term and an action are told apart by their name, and a type's own row wins
     over the block's. */
  public function description(): FieldDefinition
  {
    return $this->f->longText('description', 'Description');   // plain text
  }

  /* An image of the thing, never a place to park what a page needs: an icon
     that follows from a controlled value is the theme's, as a webfont is.
     Without ‡ like the description: a picture opens the first screen where one
     is ordinary work, and an invoice, a message or a term starts without. */
  public function image(): FieldDefinition
  {
    return $this->f->image('image', 'Image')->repeatable();
  }

  /* `inLanguage` is the spine's, and only a forking type emits it, so on
     any other the field would be a column that is always empty.
     @return list<FieldDefinition> */
  public function creativeWork(LocaleMode $locale): array
  {
    $f = $this->f;
    $vo = $this->vo;
    $language = $locale->forks() ? [$f->text('in_language', 'Language')->derived(Derivation::spine())] : [];

    return [
      $f->text('headline', 'Headline')->words(),
      $f->longText('abstract', 'Abstract'),
      $f->markdown('text', 'Body'),
      $f->date('date_published', 'Published'),
      $f->dateTime('date_modified', 'Modified'),
      $f->dateTime('date_created', 'Created'),
      ...$language,
      $f->link('license', 'Licence'),
      $f->boolean('is_accessible_for_free', 'Free to read'),
      $f->link('thumbnail_url', 'Thumbnail'),
      $f->ref('copyright_holder', 'Copyright holder', 'Person', 'Organization'),
      $f->number('copyright_year', 'Copyright year'),
      $f->refs('author', 'Author', 'Person', 'Organization'),
      $f->ref('publisher', 'Publisher', 'Person', 'Organization'),
      $f->refs('about', 'About', '*'),
      $f->refs('mentions', 'Mentions', '*'),
      $f->term('keywords', 'Keywords')->repeatable(),
      $f->term('genre', 'Genre'),
      ...$vo->flatten('PeopleAudience', 'audience', 'Audience', $f),
      /* A part names its whole. What stays at the whole's end is its lists, since
         an ItemList is no work and cannot say what it is part of. */
      $f->ref('is_part_of', 'Part of', 'CreativeWork'),
      $f->refs('has_part', 'Lists', 'ItemList'),
      $f->refs('is_based_on', 'Based on', 'CreativeWork'),
      $f->refs('citation', 'Citation', 'CreativeWork'),
      $f->ref('video', 'Video', 'VideoObject'),
      /* ∂ where the reviews are rows here, typed where they live at a platform
         this house cannot read: the holdings decide per record. */
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
    ];
  }
}
