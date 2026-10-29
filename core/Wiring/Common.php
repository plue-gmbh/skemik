<?php

declare(strict_types=1);

namespace Wiring;

use Admin\Cells;
use Admin\DataLayout;
use Admin\ErrorLog;
use Admin\Frame;
use Admin\Grants;
use Admin\LiveKeys;
use Admin\Intake;
use Admin\KeyFacts;
use Admin\LocaleController;
use Admin\Moments;
use Admin\Premises;
use Admin\Probes;
use Admin\Proofs;
use Admin\Rows;
use Admin\Sheet;
use Admin\Showing;
use Admin\Sidebar;
use Auth\Account;
use Auth\AccountRepository;
use Auth\Authorizer;
use Auth\GrantRepository;
use Auth\LoginCode;
use Auth\NotAuthenticated;
use Auth\Session;
use Auth\Smtp;
use Auth\Token;
use Auth\TokenRepository;
use Domain\Entity;
use Feed\FeedRequest;
use Feed\PublicFilter;
use Feed\Targets;
use Http\Csrf;
use Http\Request;
use Profile\EdgeValidator;
use Profile\Predicate;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\Seed;
use Profile\Seed\Terms;
use Profile\Validator;
use Profile\Vocabulary;
use Storage\ActivityLog;
use Storage\CollectionStore;
use Storage\Collisions;
use Storage\Connection;
use Storage\Counts;
use Storage\EntityRepository;
use Storage\RateLimit;
use Storage\RelationRepository;
use Storage\Search;
use Storage\SettingStore;
use Storage\Sites;
use Support\Clock;
use Support\Fetch;
use Support\Json;
use Support\Template;
use Support\Thumbnail;
use Support\Uuid;
use Support\Words;
use Update\Backup;
use Update\Downloader;
use Update\FileCheck;
use Update\Installer;
use Update\Release;
use Update\Signature;
use Update\UpdateCheck;
use Update\Updater;

/* What more than one area answers with, built once per request in the order it
   depends on: the actor before the holdings, because `ActivityLog` binds it when
   built, so no write can be attributed to anybody else. A builder moves here when
   a second area needs it, and not before. It is a bag of the house's objects,
   which becomes a service locator the moment anything below `Wiring` receives
   it, so nothing outside this folder names it. */
final readonly class Common
{
  public Profile $profile;
  public SchemaOrg $schema;
  public Predicate $predicates;
  public Validator $validator;
  public Terms $terms;
  public \PDO $db;
  public SettingStore $settings;
  public Clock $clock;
  public Uuid $uuid;
  public AccountRepository $accounts;
  public TokenRepository $tokens;
  public GrantRepository $grants;
  public Authorizer $authorizer;
  public Csrf $csrf;
  public ?Account $account;
  public Words $words;
  public RelationRepository $relations;
  public Collisions $collisions;
  public Search $search;
  public ?Entity $person;
  public ActivityLog $log;
  public EntityRepository $entities;
  public CollectionStore $collections;
  public Counts $counts;
  public PublicFilter $filter;
  public Sidebar $sidebar;
  public Rows $rows;
  public Moments $moments;
  public FeedRequest $parameters;
  public Thumbnail $thumbnails;
  public Profile $named;
  public Targets $targets;

  public function __construct(
    public string $root,
    public Premises $premises,
    public ?Smtp $relay,
    public Request $request,
    public Session $session,
    public Template $template,
    private Clock $utc,
  ) {
    $this->model();
    $this->house();
    $this->asker();
    $this->holdings();
    $this->screens();
  }

  public function requirePerson(): Entity
  {
    return $this->person ?? throw NotAuthenticated::human();
  }

  public function entitiesFor(Entity|Token|null $actor): EntityRepository
  {
    return new EntityRepository($this->db, $this->profile, $this->schema,
      $this->collisions, $this->logFor($actor), $this->relations, $this->search, $this->clock);
  }

  public function logFor(Entity|Token|null $actor): ActivityLog
  {
    return new ActivityLog($this->db, $actor, $this->uuid, $this->clock);
  }

  public function limit(int $count, int $seconds): RateLimit
  {
    return new RateLimit($this->db, $this->uuid, $this->clock, $count, $seconds);
  }

  public function codes(): LoginCode
  {
    return new LoginCode($this->db, $this->accounts, $this->uuid, $this->clock);
  }

  public function backup(): Backup
  {
    return new Backup($this->premises->data, $this->db, $this->clock, $this->premises->dailyBackups);
  }

  public function files(): FileCheck
  {
    return new FileCheck($this->root, $this->backup(), $this->clock);
  }

  public function errors(): ErrorLog
  {
    return new ErrorLog($this->premises->data . '/log');
  }

  public function probes(): Probes
  {
    return new Probes($this->premises->data,
      new DataLayout($this->root, new Proofs(new Fetch(), $this->words), $this->words),
      new Fetch(), $this->utc, $this->words, $this->updates());
  }

  public function updates(): UpdateCheck
  {
    return new UpdateCheck($this->premises->data, new Fetch(), $this->clock);
  }

  public function updater(): Updater
  {
    return new Updater($this->premises->updates, $this->premises->early, $this->premises->data,
      $this->premises->address, $this->updates(), new Downloader($this->premises->data . '/staging', new Fetch()),
      new Signature(Release::KEY), new Installer($this->root, $this->backup()), $this->backup(), new Fetch(),
      $this->clock);
  }

  public function frame(): Frame
  {
    return new Frame($this->sidebar, $this->probes(), $this->session, $this->rows, $this->collections,
      $this->settings, $this->csrf, $this->template, $this->words);
  }

  public function sheet(): Sheet
  {
    return new Sheet($this->rows, new Cells($this->template, $this->words, $this->terms, $this->clock,
      $this->moments, $this->profile), $this->collections, $this->terms, $this->profile, $this->schema,
      $this->settings, $this->clock, $this->moments, $this->words);
  }

  public function reviews(): Grants
  {
    return new Grants($this->grants, $this->keys(), $this->entities, $this->collections, $this->log,
      $this->rows, $this->named, $this->facts(), $this->clock, $this->words);
  }

  public function keys(): LiveKeys
  {
    return new LiveKeys($this->tokens, $this->facts(), $this->clock, $this->moments, $this->words);
  }

  public function facts(): KeyFacts
  {
    return new KeyFacts($this->entities, $this->clock, $this->moments, $this->words);
  }

  public function showing(): Showing
  {
    return new Showing($this->relations, $this->entities, $this->profile, $this->schema);
  }

  public function intake(): Intake
  {
    return new Intake($this->entities, $this->validator, $this->profile, $this->settings, $this->uuid,
      $this->clock, $this->words, $this->thumbnails, $this->collisions, $this->premises->data . '/holdings/media');
  }

  public function locales(): LocaleController
  {
    return new LocaleController($this->authorizer, $this->settings, $this->session, $this->csrf,
      $this->words, $this->log);
  }

  private function model(): void
  {
    $this->profile = (new Seed())->profile();
    $this->schema = new SchemaOrg($this->profile, new Vocabulary((new Json())->readFile(Vocabulary::FILE)));
    $this->predicates = new Predicate($this->profile, $this->schema);
    $this->validator = new Validator($this->profile, new EdgeValidator($this->schema));
  }

  private function house(): void
  {
    $this->db = (new Connection())->open($this->premises->data . '/holdings/data.sqlite');
    $this->settings = new SettingStore($this->db, $this->utc, $this->profile);
    $this->clock = new Clock($this->settings->timezone());
    $this->uuid = new Uuid($this->clock);
  }

  private function asker(): void
  {
    $db = $this->db;
    $this->accounts = new AccountRepository($db, $this->uuid, $this->clock);
    $this->tokens = new TokenRepository($db, $this->clock);
    $this->grants = new GrantRepository($db, $this->tokens);
    $this->authorizer = new Authorizer($this->session, $this->accounts, $this->tokens, $this->grants,
      $this->request->bearer());
    $this->csrf = new Csrf($this->session);
    $this->account = $this->authorizer->current();
    $this->words = new Words($this->root . '/language', $this->request->header('Accept-Language') ?? '',
      $this->settings->chromeLocale()?->tag);
  }

  private function holdings(): void
  {
    $db = $this->db;
    $this->relations = new RelationRepository($db, $this->predicates, $this->uuid);
    $this->collisions = new Collisions($db, $this->profile, $this->schema);
    $this->search = new Search($db, $this->profile, $this->schema, $this->clock);
    $this->person = $this->account === null ? null : $this->entitiesFor(null)->find($this->account->person);
    $this->log = new ActivityLog($db, $this->person, $this->uuid, $this->clock);
    $this->entities = $this->entitiesFor($this->person);
    $this->collections = new CollectionStore($db, $this->profile, $this->uuid, $this->words);
    $this->counts = new Counts($db, $this->clock);
  }

  private function screens(): void
  {
    $this->filter = new PublicFilter($this->profile, $this->clock);
    $this->sidebar = new Sidebar($this->collections, $this->counts,
      new Sites($this->db, $this->clock, $this->settings), $this->authorizer, $this->filter, $this->grants,
      $this->clock, $this->words);
    $this->rows = new Rows($this->relations, $this->entities, $this->authorizer, $this->filter,
      $this->profile, $this->schema, $this->clock);
    $this->moments = new Moments($this->clock, $this->words);
    $this->parameters = new FeedRequest($this->schema, $this->profile, $this->clock);
    $this->thumbnails = new Thumbnail($this->premises->data . '/cache/thumbs');
    /* The screens name a field in the admin's language; validation, storage and
       the channels keep the seed's English. */
    $this->named = $this->profile->worded($this->words->fields($this->words->language));
    $this->terms = new Terms($this->profile, $this->words->terms());
    $this->targets = new Targets($this->entities, $this->relations, $this->collections, $this->profile,
      $this->schema, $this->request->origin());
  }
}
