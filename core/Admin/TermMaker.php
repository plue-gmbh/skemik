<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\EntityId;
use Domain\Locale;
use Domain\Props;
use Domain\Relation;
use Domain\Slug;
use Domain\Status;
use Feed\Targets;
use Profile\Predicate;
use Storage\EntityRepository;
use Support\Clock;
use Support\Uuid;

/* A word typed into a term field becomes the term of that word in the field's
   set, made where the set lacks it and the set made where the house lacks it,
   so a term set grows from the record and never from a screen of its own. Both
   are published as made: a house's vocabulary is not a draft, and a draft term
   would leave the key out of every stranger's document. The word is the code,
   since a code is what the feed says and a house names its own things; a word
   the set holds in another case is that term, as the picker offers it. */
final readonly class TermMaker
{
  public function __construct(
    private EntityRepository $entities,
    private Targets $targets,
    private Predicate $predicates,
    private Locale $locale,
    private Uuid $uuid,
    private Clock $clock,
  ) {}

  /** @param string $setName what the set is called when it has to be made: the field's label */
  public function made(string $set, string $setName, string $word): Entity
  {
    $root = $this->targets->root($set) ?? $this->make($set, $setName, []);
    $edge = fn (EntityId $subject): Relation => new Relation($this->predicates, $subject,
      'inDefinedTermSet', $root->id, 'DefinedTerm', $set);

    return $this->targets->worded($set, $word) ?? $this->make($word, $word, [$edge]);
  }

  /** @param list<callable(EntityId): Relation> $edges */
  private function make(string $code, string $name, array $edges): Entity
  {
    $id = new EntityId($this->uuid->generate());
    $now = $this->clock->now()->format(Clock::STORED);
    $term = new Entity($id, 'https://schema.org/DefinedTerm', $this->locale,
      Slug::from($name, 'term'), new Props(['name' => $name, 'term_code' => $code]), $now, $now,
      Status::Published);

    return $this->entities->save($term, array_map(fn (callable $edge): Relation => $edge($id), $edges));
  }
}
