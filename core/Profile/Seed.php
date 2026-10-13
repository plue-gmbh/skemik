<?php

declare(strict_types=1);

namespace Profile;

use Profile\Seed\Agents;
use Profile\Seed\Commerce;
use Profile\Seed\Editorial;
use Profile\Seed\Inbound;
use Profile\Seed\Media;
use Profile\Seed\Structure;
use Profile\Seed\Time;
use Profile\Seed\Trade;
use Profile\Seed\ValueObjects;

/* The thirty types as data.
   The only copy: the types are the app's data and travel with the code,
   so a release that changes a type changes it here and nothing is written to a
   database.
   Nothing here reads or writes anything, which is what lets Claims run off an
   install — and it is the file to open to ask what a type holds. */
final readonly class Seed
{
  public function __construct(
    private Agents $agents = new Agents(),
    private Editorial $editorial = new Editorial(),
    private Media $media = new Media(),
    private Inbound $inbound = new Inbound(),
    private Commerce $commerce = new Commerce(),
    private Trade $trade = new Trade(),
    private Time $time = new Time(),
    private Structure $structure = new Structure(),
    private ValueObjects $valueObjects = new ValueObjects(),
  ) {}

  public function profile(): Profile
  {
    $types = [
      ...$this->agents->types(),
      ...$this->editorial->types(),
      ...$this->media->types(),
      ...$this->inbound->types(),
      ...$this->commerce->types(),
      ...$this->trade->types(),
      ...$this->time->types(),
      ...$this->structure->types(),
    ];

    /* The ceiling is 30 and all thirty slots are spent. A thirty-first
       is a decision about the product, and it does not arrive by accident: a
       new requirement tries `additionalType`, then `additionalProperty`, then an
       `ItemList`, then a `DefinedTerm` set, and only a thing that needs its own
       address, lifecycle and permissions has an argument at all. A value object
       and an edge qualifier are no rungs of that ladder but how the profile
       ordinarily talks. */
    assert(count($types) === Profile::CEILING, 'the profile is not thirty types');

    return new Profile($types, $this->valueObjects->all());
  }
}
