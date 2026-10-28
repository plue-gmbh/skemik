<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRepository;
use Auth\CodeDelivery;
use Auth\LoginCode;
use Auth\Role;
use Auth\Session;
use Auth\TypeScope;
use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\Props;
use Domain\Slug;
use Http\RouteNotFound;
use Profile\Profile;
use Profile\Validator;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Migrator;
use Storage\SettingStore;
use Storage\Transaction;
use Support\Clock;
use Support\Uuid;

/* The house a first run makes, in one transaction so a failure leaves no
   half-made owner: the settings, its language among them before any record is
   written in one, the `Person`, the one `owner` account beside it and all
   thirty collections — the product, not a choice. Nothing else is
   seeded, since an example record is the first day spent tidying up. The owner
   is a draft like any new record: a name is not published by being typed here.
   The account check sits inside the write lock, so two first runs posted at once
   make one owner and a 404. */
final readonly class Founding
{
  public function __construct(
    private \PDO $db,
    private Migrator $migrator,
    private EntityRepository $entities,
    private AccountRepository $accounts,
    private CollectionStore $collections,
    private SettingStore $settings,
    private Validator $validator,
    private Profile $profile,
    private Session $session,
    private LoginCode $codes,
    private CodeDelivery $delivery,
    private Uuid $uuid,
    private Clock $clock,
    private Transaction $transaction = new Transaction(),
  ) {}

  public function prepare(): void
  {
    $this->migrator->prepare($this->db);
  }

  public function found(InstallForm $form): Account
  {
    $owner = null;
    $this->transaction->run($this->db, function () use ($form, &$owner): void {
      if ($this->accounts->all() !== []) {
        throw RouteNotFound::for('POST', '/install');
      }
      $this->settings->setLocales([$form->publishing]);
      $person = $this->entities->save($this->person($form->name), []);
      $owner = $this->accounts->create($person, $form->email, Role::Owner, TypeScope::all(), TypeScope::all());
      $this->collections->provide();
      $this->settings->setTimezone($form->timezone);
      $this->settings->setChromeLocale($form->language);
    });

    return $owner;
  }

  /* The first code, bound to the browser that ran the install and written to
     `login/` whatever the mail did, so a Webspace whose mail never worked still
     hands the house over. Answers the cookie that carries the session. */
  public function handOver(Account $owner, LoginMail $mail): string
  {
    $this->session->begin();
    $this->delivery->send($owner, $mail->with($this->codes->issue($owner, $this->session->key())));
    $this->session->keep('handed', 'yes');

    return $this->session->cookie();
  }

  private function person(string $name): Entity
  {
    $type = $this->profile->byType('Person');
    $props = new Props(['name' => $name]);
    $violations = $this->validator->check($type, $props->document(), []);
    if ($violations !== []) {
      throw MalformedValue::named($violations[0]->field, $violations[0]->said());
    }
    $now = $this->clock->now()->format(Clock::STORED);

    return new Entity(new EntityId($this->uuid->generate()), $type->url(),
      $this->settings->locales()[0], Slug::from($name, 'person'), $props, $now, $now);
  }
}
