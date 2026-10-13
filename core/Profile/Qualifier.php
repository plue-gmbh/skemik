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

  /* A membership's own two, on an edge to a `MemberProgram` and no other. */
  public function ofMembership(): bool
  {
    return in_array($this, [self::MembershipNumber, self::MembershipPointsEarned], true);
  }

  public static function membership(string $predicate, ?string $objectType): bool
  {
    return $predicate === 'memberOf' && $objectType === 'MemberProgram';
  }

  /* What a screen writes on such an edge, each a line of its own read as its
     kind: all but `potentialAction`, which stays as the feed sent it.
     @return array<string, FieldKind> */
  public static function lines(string $predicate, ?string $objectType): array
  {
    $lines = [];
    foreach (self::cases() as $qualifier) {
      if ($qualifier !== self::PotentialAction
        && (!$qualifier->ofMembership() || self::membership($predicate, $objectType))) {
        $lines[$qualifier->value] = $qualifier->kind();
      }
    }

    return $lines;
  }
}
