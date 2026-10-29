<?php

declare(strict_types=1);

namespace Wiring;

use Admin\DataLayout;
use Admin\Founding;
use Admin\InstallController;
use Admin\Premises;
use Admin\Proofs;
use Auth\AccountRepository;
use Auth\CodeDelivery;
use Auth\LoginCode;
use Auth\Session;
use Http\Request;
use Http\Response;
use Http\Route;
use Profile\EdgeValidator;
use Profile\Predicate;
use Profile\SchemaOrg;
use Profile\Seed;
use Profile\Validator;
use Profile\Vocabulary;
use Storage\ActivityLog;
use Storage\CollectionStore;
use Storage\Collisions;
use Storage\Connection;
use Storage\EntityRepository;
use Storage\Migrator;
use Storage\RelationRepository;
use Storage\Schema;
use Storage\Search;
use Storage\SettingStore;
use Support\Clock;
use Support\Fetch;
use Support\Json;
use Support\Template;
use Support\Uuid;
use Support\Words;

/* The first run's objects, apart from `Common`: before `config.php` exists
   nothing of it can be built, and a run stopped after writing it resumes before
   reading tables it may never have made. */
final readonly class FirstRun
{
  public function __construct(
    private string $root,
    private Request $request,
    private Template $template,
    private Words $words,
    private Clock $utc,
  ) {}

  public function answer(Route $route): Response
  {
    return $this->install(true, null)->answer($route->endpoint, $this->request);
  }

  public function resumed(Route $route, Premises $premises): Response
  {
    $db = (new Connection())->open($premises->data . '/holdings/data.sqlite');
    $accounts = new AccountRepository($db, new Uuid($this->utc), $this->utc);
    /* No table or no account, never the shape: a house mid-move differs from it and has an owner. */
    $unfinished = (new Schema((new Seed())->profile()))->empty($db) || $accounts->all() === [];
    return $this->install($unfinished, $premises)->answer($route->endpoint, $this->request);
  }

  private function install(bool $firstRun, ?Premises $premises): InstallController
  {
    return new InstallController($firstRun, $premises,
      new DataLayout($this->root, new Proofs(new Fetch(), $this->words), $this->words), $this->founding(...),
      $this->template, $this->words);
  }

  /* The house a first run founds, built for the folder it has just settled. */
  private function founding(Premises $premises): Founding
  {
    [$uuid, $utc] = [new Uuid($this->utc), $this->utc];
    $db = (new Connection())->open($premises->data . '/holdings/data.sqlite');
    $profile = (new Seed())->profile();
    $schema = new SchemaOrg($profile, new Vocabulary((new Json())->readFile(Vocabulary::FILE)));
    $accounts = new AccountRepository($db, $uuid, $utc);
    $entities = new EntityRepository($db, $profile, $schema,
      new Collisions($db, $profile, $schema), new ActivityLog($db, null, $uuid, $utc),
      new RelationRepository($db, new Predicate($profile, $schema), $uuid),
      new Search($db, $profile, $schema, $utc), $utc);
    return new Founding($db, new Migrator(new Schema($profile), $this->root . '/migrations'), $entities,
      $accounts, new CollectionStore($db, $profile, $uuid, $this->words),
      new SettingStore($db, $utc, $profile), new Validator($profile, new EdgeValidator($schema)), $profile,
      new Session($premises->data . '/sessions', $this->request->https, $utc),
      new LoginCode($db, $accounts, $uuid, $utc), new CodeDelivery($premises->data . '/login', null),
      $uuid, $utc);
  }
}
