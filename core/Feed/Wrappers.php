<?php

declare(strict_types=1);

namespace Feed;

use Domain\Relation;
use Profile\Qualifier;
use Support\Rendered;

/* An edge's words in the wrapper schema.org keeps for what a surface says about
   its target: a `ListItem` for a list's member, a `ProgramMembership` for a
   membership, there whether or not it says anything, since a membership and not
   its programme is what `memberOf` holds, and a `Role` for the rest. A role's own
   words go into a `Role` around the target where the wrapper declares none of
   them, so a team list's *Managing Director* stands on the class that has it.
   `Ingest\Edges` reads the same shapes back. */
final readonly class Wrappers
{
  /**
   * @param array<string, mixed> $words the edge's words in the reader's language
   * @param array<string, mixed>|string|Rendered $target
   * @return array<string, mixed>|string|Rendered
   */
  public function wrapped(Relation $edge, array $words, array|string|Rendered $target): array|string|Rendered
  {
    $wrapper = Qualifier::wrapper($edge->predicate, $edge->objectType);
    if ($wrapper === 'Role') {
      return $this->role($words, $edge->predicate, $target);
    }
    $role = array_intersect_key($words, array_flip(Qualifier::ROLE));
    $own = array_diff_key($words, $role);
    if ($wrapper === 'ListItem') {
      $position = $edge->position === null ? [] : ['position' => $edge->position];

      return ['@type' => 'ListItem', ...$position, ...$own, 'item' => $this->role($role, 'item', $target)];
    }

    return $this->role($role, $edge->predicate, ['@type' => $wrapper, ...$own, 'program' => $target]);
  }

  /**
   * @param array<string, mixed> $words
   * @param array<string, mixed>|string|Rendered $target
   * @return array<string, mixed>|string|Rendered
   */
  private function role(array $words, string $property, array|string|Rendered $target): array|string|Rendered
  {
    return $words === [] ? $target : ['@type' => 'Role', ...$words, $property => $target];
  }
}
