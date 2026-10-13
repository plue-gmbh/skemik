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

/* The two types with a clock. They sit one edge apart on every bookable
   date: the reservations are the root fact, the Event's free places follow
   from them, and the Offer's availability follows from nobody. */
final readonly class Time
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
      new TypeDefinition('Event', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->event(), $this->base->thing()),
      new TypeDefinition('Reservation', Erasure::Hold, LocaleMode::None, Reach::House,
        $this->reservation(), $this->base->thing()),
    ];
  }

  /** @return list<FieldDefinition> */
  private function event(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      /* ‡ and not †, the one override in the profile: a concert names itself,
         the eleventh run of a course does not, and requiring a name there
         produces a formatted restatement no page renders. */
      $f->text('name', 'Name')->starter()->words(),
      $f->dateTime('start_date', 'Starts')->required(),
      $f->dateTime('end_date', 'Ends')->starter(),
      $this->base->image()->starter(),
      $f->duration('duration', 'Duration'),
      $f->dateTime('door_time', 'Doors open'),
      $f->dateTime('previous_start_date', 'Previous start'),
      $f->select('event_status', 'Status', ['EventScheduled', 'EventCancelled',
        'EventPostponed', 'EventRescheduled', 'EventMovedOnline'])->starter(),
      $f->select('event_attendance_mode', 'Attendance', ['OfflineEventAttendanceMode',
        'OnlineEventAttendanceMode', 'MixedEventAttendanceMode'])->starter(),
      $f->objects('event_schedule', 'Recurrence', 'Schedule'),
      $f->number('maximum_attendee_capacity', 'Capacity'),
      /* ∂ — capacity minus every reservation not cancelled, computed at emit. The
         editor offers no input for a number every booking overwrites. */
      $f->number('remaining_attendee_capacity', 'Places left')
        ->derived(Derivation::over(Derived::Count, 'reservation_for')->inbound()
          ->minus('maximum_attendee_capacity')
          ->lapsesAt('reservation_status', 'ReservationCancelled')),
      $f->number('maximum_virtual_attendee_capacity', 'Online capacity'),
      $f->number('maximum_physical_attendee_capacity', 'On-site capacity'),
      $f->boolean('is_accessible_for_free', 'Free of charge'),
      $f->text('typical_age_range', 'Age range'),
      $f->listed('in_language', 'Language', ClosedList::Language),
      $f->term('keywords', 'Keywords')->repeatable(),
      /* One property in two fields. A hybrid event — a room plus a
         stream — is ordinary, and one field admitting either can express it
         only by choosing. Both optional: the profile holds no rule spanning
         two fields, so *at least one* is not ours to enforce. */
      $f->ref('location', 'Venue', 'Place')->starter(),
      $f->link('location_url', 'Online address')->starter()
        ->keyOf('VirtualLocation', 'url', 'location'),
      $f->ref('organizer', 'Organizer', 'Organization', 'Person')->starter(),
      $f->refs('performer', 'Performer', 'Person', 'Organization'),
      $f->refs('attendee', 'Attendee', 'Person'),
      ...$vo->flatten('PeopleAudience', 'audience', 'Audience', $f),
      $f->refs('offers', 'Tickets', 'Offer')
        ->derived(Derivation::over(Derived::Range, 'item_offered')->inbound()),
      ...$vo->flatten('AggregateRating', 'aggregateRating', 'Rating', $f, computed: true),
      $f->ref('super_event', 'Part of', 'Event'),
      $f->ref('about', 'About', '*'),
      $f->ref('work_featured', 'Work featured', 'CreativeWork'),
      $f->ref('work_performed', 'Work performed', 'CreativeWork'),
      $f->ref('recorded_in', 'Recording', 'MediaObject'),
    ];
  }

  /** @return list<FieldDefinition> */
  private function reservation(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->text('reservation_id', 'Reference')->starter(),
      /* The lifecycle Event.remainingAttendeeCapacity counts through. */
      $f->select('reservation_status', 'Status', ['ReservationConfirmed',
        'ReservationPending', 'ReservationHold', 'ReservationCancelled'])->starter(),
      $f->dateTime('booking_time', 'Booked'),
      $f->dateTime('modified_time', 'Modified'),
      $f->number('total_price', 'Price')->starter(),
      $f->listed('price_currency', 'Currency', ClosedList::Currency)->starter(),
      $f->ref('reservation_for', 'Reserved for', 'Event', 'Service', 'Place', 'Product')
        ->required(),
      $f->ref('under_name', 'In the name of', 'Person', 'Organization')->required(),
      $f->ref('provider', 'Provider', 'Organization', 'Person'),
      $f->ref('broker', 'Broker', 'Organization', 'Person'),
      /* Off-range on purpose: a membership is the qualified edge from a
         Person to its program and has no address, so this edge names the
         program. */
      $f->ref('program_membership_used', 'Membership used', 'MemberProgram'),
      ...$vo->flatten('Ticket', 'reservedTicket', 'Ticket', $f),
    ];
  }
}
