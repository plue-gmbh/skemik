<?php

declare(strict_types=1);

namespace Wiring;

use Admin\Premises;
use Profile\SchemaOrg;
use Profile\Seed;
use Profile\Vocabulary;
use Storage\CollectionStore;
use Storage\Connection;
use Storage\Migrator;
use Storage\Rewrite;
use Storage\Schema;
use Storage\Search;
use Support\Clock;
use Support\Json;
use Support\Uuid;
use Support\Words;
use Update\Move;

/* The move the holdings make to the running version (`Update\Move`), built
   before `Common` opens them, since what it reads must be moved first; a
   restore is handed the same one. Each part is a recipe, so the profile is built
   when a move runs and not on the request that asks whether one is due. */
final readonly class Arrival
{
  public function __construct(
    private string $root,
    private Premises $premises,
    private Clock $clock,
    private Words $words,
  ) {}

  public function move(): Move
  {
    return new Move($this->premises->data, $this->root . '/migrations', new Connection(), $this->migrator(...),
      $this->collections(...), $this->rewrite(...), $this->words);
  }

  private function migrator(): Migrator
  {
    return new Migrator(new Schema((new Seed())->profile()), $this->root . '/migrations');
  }

  private function collections(\PDO $db): CollectionStore
  {
    return new CollectionStore($db, (new Seed())->profile(), new Uuid($this->clock),
      $this->words);
  }

  private function rewrite(\PDO $db): Rewrite
  {
    $profile = (new Seed())->profile();
    $schema = new SchemaOrg($profile, new Vocabulary((new Json())->readFile(Vocabulary::FILE)));

    return new Rewrite($db, $profile,
      new Search($db, $profile, $schema, $this->clock));
  }
}
