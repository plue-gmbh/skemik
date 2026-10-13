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

/* Structure, vocabulary and the specialised four. The first two are the
   collection-killers: a list replaces every custom collection, and a term set
   replaces every taxonomy table. */
final readonly class Structure
{
  private const ANSWERED = [
    'accepted_answer_text' => 'Accepted answer',
    'accepted_answer_author' => 'Accepted answer by',
    'accepted_answer_url' => 'Accepted answer address',
    'accepted_answer_upvotes' => 'Accepted answer upvotes',
  ];

  public function __construct(
    private Base $base = new Base(),
    private Fields $f = new Fields(),
    private ValueObjects $vo = new ValueObjects(),
  ) {}

  /** @return list<TypeDefinition> */
  public function types(): array
  {
    $thing = $this->base->thing();

    return [
      new TypeDefinition('ItemList', Erasure::Purge, LocaleMode::Fork, Reach::Public,
        $this->itemList(), $thing),
      new TypeDefinition('DefinedTerm', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->definedTerm(), $thing),
      new TypeDefinition('JobPosting', Erasure::Purge, LocaleMode::Fork, Reach::Public,
        $this->jobPosting(), $thing),
      new TypeDefinition('SoftwareApplication', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->softwareApplication(),
        [...$this->base->creativeWork(LocaleMode::Localise), ...$thing]),
      /* An FAQ entry the house writes is the expected case, so it forks like
         any work. */
      new TypeDefinition('Question', Erasure::Purge, LocaleMode::Fork, Reach::Public,
        $this->question(), $thing),
      /* The kind is the specialisation: work somebody owes, and with
         `ExerciseAction` what a member did, which a training app records as
         completed. It is a row and not a line of the log because a wrongly
         logged workout is corrected, and the log takes no correction. */
      new TypeDefinition('Action', Erasure::Purge, LocaleMode::None, Reach::Public,
        $this->action(), $thing),
    ];
  }

  /** @return list<FieldDefinition> */
  private function itemList(): array
  {
    $f = $this->f;

    return [
      /* ∂ — the array has a length. Order leaves the house in the edge's
         position and nowhere else: a JSON-LD array is an unordered set. */
      $f->number('number_of_items', 'Items')
        ->derived(Derivation::over(Derived::Count, 'item_list_element')),
      $f->refs('item_list_element', 'Members', '*')->required(),
      $this->base->description()->starter(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function definedTerm(): array
  {
    $f = $this->f;

    return [
      /* Unique per set, since a write names a term by its code alone: a second
         `EUR` beside the first leaves the code naming neither. A term with no set
         is a set, a root, so a house makes its first one with one record; the
         roots' codes are unique among the roots. */
      $f->text('term_code', 'Code')->required()->unique('in_defined_term_set'),
      $f->ref('in_defined_term_set', 'Term set', 'DefinedTerm')->starter(),
      /* SKOS, not schema.org, and `Claims` names it as its exception,
         because a taxonomy without a hierarchy is a flat list. */
      $f->ref('broader', 'Broader', 'DefinedTerm'),
      $f->objects('additional_property', 'Additional property', 'PropertyValue')->words(),
      $this->base->description(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function jobPosting(): array
  {
    $f = $this->f;

    return [
      $f->text('name', 'Name')->words(),               // title is the posting's name
      $f->text('title', 'Title')->required()->words(),
      $f->longText('description', 'Description')->starter(),
      $f->dateTime('date_posted', 'Posted')->starter(),
      $f->date('valid_through', 'Valid through'),
      $f->select('employment_type', 'Employment type', ['FULL_TIME', 'PART_TIME',
        'CONTRACTOR', 'INTERN', 'TEMPORARY', 'VOLUNTEER'])->repeatable()->starter(),
      $f->select('job_location_type', 'Location type', ['TELECOMMUTE', 'ONSITE', 'HYBRID']),
      $f->number('base_salary', 'Salary')->starter()
        ->keyOf('MonetaryAmount', 'value', 'baseSalary'),
      $f->listed('salary_currency', 'Currency', ClosedList::Currency)->starter(),
      /* A field of its own, since without it the perks end up as prose in
         workHours and description, which is not a rung. */
      $f->longText('job_benefits', 'Benefits')->starter(),
      $f->longText('responsibilities', 'Responsibilities'),
      /* Terms, so a skill filters across postings; the prose is `qualifications`. */
      $f->term('skills', 'Skills')->repeatable(),
      $f->longText('qualifications', 'Qualifications'),
      $f->longText('experience_requirements', 'Experience required'),
      $f->longText('education_requirements', 'Education required'),
      $f->text('work_hours', 'Working hours'),
      $f->date('job_start_date', 'Start date'),
      $f->number('total_job_openings', 'Openings'),
      $f->boolean('direct_apply', 'Direct apply'),
      $f->term('occupational_category', 'Occupational category', 'occupation'),
      $f->term('industry', 'Industry'),
      $f->term('applicant_location_requirements', 'Applicant location', 'region')->repeatable(),
      $f->ref('hiring_organization', 'Employer', 'Organization')->starter(),
      $f->refs('job_location', 'Workplace', 'Place')->starter(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function softwareApplication(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      /* The twenty-two a search engine reads, since a desktop program has no kind
         and says what it is here. */
      $f->select('application_category', 'Category', [
        'GameApplication', 'SocialNetworkingApplication', 'TravelApplication',
        'ShoppingApplication', 'SportsApplication', 'LifestyleApplication', 'BusinessApplication',
        'DesignApplication', 'DeveloperApplication', 'DriverApplication',
        'EducationalApplication', 'HealthApplication', 'FinanceApplication',
        'SecurityApplication', 'BrowserApplication', 'CommunicationApplication',
        'DesktopEnhancementApplication', 'EntertainmentApplication', 'MultimediaApplication',
        'HomeApplication', 'UtilitiesApplication', 'ReferenceApplication'])->starter(),
      $f->term('application_sub_category', 'Sub-category'),
      /* Not ‡: a web app has none, and a term field wants a set first. */
      $f->term('operating_system', 'Operating system')->repeatable(),
      $f->text('software_version', 'Version')->starter(),
      $f->link('license', 'Licence')->starter(),
      $this->base->image()->starter(),
      $f->longText('release_notes', 'Release notes'),
      $f->link('download_url', 'Download')->starter(),
      $f->link('install_url', 'Install'),
      $f->text('file_size', 'File size'),
      $f->longText('feature_list', 'Features')->repeatable(),
      $f->text('software_requirements', 'Software requirements'),
      $f->text('memory_requirements', 'Memory requirements'),
      $f->text('storage_requirements', 'Storage requirements'),
      $f->text('processor_requirements', 'Processor requirements'),
      $f->text('permissions', 'Permissions'),
      $f->listed('countries_supported', 'Countries supported', ClosedList::Country)->repeatable(),
      $f->listed('countries_not_supported', 'Countries not supported', ClosedList::Country)
        ->repeatable(),
      $f->refs('screenshot', 'Screenshots', 'MediaObject'),
      $f->refs('software_add_on', 'Add-ons', 'SoftwareApplication'),
      $f->refs('offers', 'Offers', 'Offer')
        ->derived(Derivation::over(Derived::Range, 'item_offered')->inbound()),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
    ];
  }

  /** @return list<FieldDefinition> */
  private function question(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->longText('text', 'Elaboration'),
      $f->number('answer_count', 'Answers'),
      $f->number('upvote_count', 'Upvotes'),
      $f->term('edu_question_type', 'Question type'),
      /* Said whole: `Accepted answer: Answer` names the block twice in a header. */
      ...array_map(fn (FieldDefinition $field): FieldDefinition => $field->labelled(self::ANSWERED[$field->key]),
        $vo->flatten('Answer', 'acceptedAnswer', 'Accepted answer', $f, starter: true)),
      $f->objects('suggested_answer', 'Suggested answer', 'Answer'),
      $f->ref('author', 'Author', 'Person'),
      $f->ref('is_part_of', 'FAQ page', 'WebPage'),
      $f->ref('about', 'About', '*'),
      /* Without ‡: an FAQ entry is its question and its answer, and a sentence
         about the question between them is a column nobody fills. */
      $this->base->description(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function action(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      /* ‡ here alone: on every other type the specialisation refines the record,
         on this one it says what kind of work it is, and a channel that records
         an `ExerciseAction` would otherwise be refused a field nobody added. */
      $f->text('additional_type', 'Kind')->repeatable()->starter(),
      $f->select('action_status', 'Status', ['PotentialActionStatus', 'ActiveActionStatus',
        'CompletedActionStatus', 'FailedActionStatus'])->starter(),
      $f->dateTime('start_time', 'Started')->starter(),
      /* Due date while potential, completion once done — actionStatus says
         which reading applies. */
      $f->dateTime('end_time', 'Due')->starter(),
      $f->text('error', 'What went wrong'),
      $f->ref('agent', 'Assignee', 'Person', 'Organization')->required(),
      $f->refs('participant', 'Participants', 'Person', 'Organization'),
      $f->ref('object', 'Concerns', '*')->starter(),
      $f->ref('result', 'Result', '*'),
      $f->ref('instrument', 'Done with', '*'),
      $f->ref('location', 'Location', 'Place'),
      ...$vo->flatten('EntryPoint', 'target', 'Entry point', $f),
      $f->objects('additional_property', 'Additional property', 'PropertyValue')->words(),
      $this->base->description(),
    ];
  }
}
