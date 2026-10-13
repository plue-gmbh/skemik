<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\ForbiddenTransition;
use Profile\Reach;
use Profile\TypeDefinition;
use Support\Clock;

/* One vocabulary for every type; only membership varies. Public is a time,
   not a flag: `published` is public inside its window and nothing rewrites it,
   so there is no `isPublic()` without an instant to reach for. A start still
   ahead is what the admin calls scheduled: a state of its own only waited for
   the date the window holds, and with none it never appeared. A record past its
   end keeps its state, the admin showing it ended, since archiving it would
   lose that it was published.
   A legal transition is any move into a state the type holds. An order between
   states would refuse ordinary work — republishing an archived page, pulling a
   published one back to review — and nothing asks for one.
   Which types hold no public state is the type's own mark, `Profile\Reach`. */
enum Status: string
{
  case Draft = 'draft';
  case Review = 'review';
  case Published = 'published';
  case Archived = 'archived';

  /** @return list<self> */
  public static function heldBy(TypeDefinition $type): array
  {
    return $type->reach === Reach::Public ? self::cases()
      : [self::Draft, self::Review, self::Archived];
  }

  public function to(self $next, TypeDefinition $type): self
  {
    if (!in_array($next, self::heldBy($type), true)) {
      throw ForbiddenTransition::notHeld($next, $type->name);
    }

    return $next;
  }

  public function isPublicAt(\DateTimeImmutable $at, ?string $publishFrom, ?string $publishTo): bool
  {
    $now = $at->setTimezone(new \DateTimeZone('UTC'))->format(Clock::STORED);
    return $this === self::Published && ($publishFrom === null || $publishFrom <= $now)
      && ($publishTo === null || $now < $publishTo);
  }
}
