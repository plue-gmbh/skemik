<?php

declare(strict_types=1);

namespace Profile;

/* What schema.org's wrappers say about a pointing, `Role`, `ListItem` and
   `ProgramMembership`, and nothing the target could say of itself: the closed
   set an edge may carry, each read as the kind a field of it would be. The
   words are the ones a reader reads in a language, so a bilingual team page
   says *Managing Director* on its English route. */
enum Qualifier: string
{
  case RoleName = 'roleName';
  case Name = 'name';
  case Description = 'description';
  case PotentialAction = 'potentialAction';
  case StartDate = 'startDate';
  case EndDate = 'endDate';
  case MembershipNumber = 'membershipNumber';
  case MembershipPointsEarned = 'membershipPointsEarned';

  /* A role's own words, which a list item and a membership do not declare. */
  public const ROLE = ['roleName', 'startDate', 'endDate'];

  public function kind(): FieldKind
  {
    return match ($this) {
      self::StartDate, self::EndDate => FieldKind::Date,
      self::MembershipPointsEarned => FieldKind::Number,
      self::PotentialAction => FieldKind::Objects,
      default => FieldKind::Text,
    };
  }

  public function holdsWords(): bool
  {
    return in_array($this, [self::RoleName, self::Name, self::Description], true);
  }

  /** @return list<string> the qualifiers that hold words */
  public static function words(): array
  {
    return array_map(fn (self $qualifier): string => $qualifier->value,
      array_values(array_filter(self::cases(), fn (self $qualifier): bool => $qualifier->holdsWords())));
  }

  /* A membership's own two, on an edge to a `MemberProgram` and no other, which
     a reservation names as the membership it used. */
  public function ofMembership(): bool
  {
    return in_array($this, [self::MembershipNumber, self::MembershipPointsEarned], true);
  }

  public static function membership(string $predicate, ?string $objectType): bool
  {
    return in_array($predicate, ['memberOf', 'programMembershipUsed'], true) && $objectType === 'MemberProgram';
  }

  /* Whether such an edge may carry this one. */
  public function heldOn(string $predicate, ?string $objectType): bool
  {
    return !$this->ofMembership() || self::membership($predicate, $objectType);
  }

  /* The wrapper an edge's words are emitted in, never on the edge's subject; a
     role's own go into a `Role` inside it (`Feed\Wrappers`). */
  public static function wrapper(string $predicate, ?string $objectType): string
  {
    return match (true) {
      $predicate === 'itemListElement' => 'ListItem',
      self::membership($predicate, $objectType) => 'ProgramMembership',
      default => 'Role',
    };
  }

  /* What it holds as schema.org names it: its buttons are rows of `Action`. */
  public function datatype(): string
  {
    return $this === self::PotentialAction ? 'Action' : $this->kind()->datatype();
  }

  /* What a screen writes on such an edge, each a line of its own read as its
     kind: all but `potentialAction`, which stays as the feed sent it.
     @return array<string, FieldKind> */
  public static function lines(string $predicate, ?string $objectType): array
  {
    $lines = [];
    foreach (self::cases() as $qualifier) {
      if ($qualifier !== self::PotentialAction && $qualifier->heldOn($predicate, $objectType)) {
        $lines[$qualifier->value] = $qualifier->kind();
      }
    }

    return $lines;
  }
}
