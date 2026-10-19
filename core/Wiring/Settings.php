<?php

declare(strict_types=1);

namespace Wiring;

use Admin\AccountController;
use Admin\ChecksController;
use Admin\ErrorLogController;
use Admin\TokenController;
use Http\Endpoint;
use Http\Response;
use Http\Route;
use Storage\Admission;
use Storage\Connection;
use Support\Zip;
use Update\BackupController;
use Update\Release;
use Update\Restore;
use Update\Signature;
use Update\UpdateController;

/* The writes behind the home screen's cards: accounts, keys and languages, which
   have no `GET` of their own, the backups screen and the error log its Settings
   card opens, the holdings check's screen Needs a look opens, and the update
   screen the header's version opens. */
final readonly class Settings
{
  public function __construct(private Common $common) {}

  public function answer(Route $route): Response
  {
    [$common, $segment] = [$this->common, $route->parameter(...)];
    return match ($route->endpoint) {
      Endpoint::AccountCreate => $this->accountController()->create($common->request),
      Endpoint::AccountEdit => $this->accountController()->edit($common->request, $segment('id')),
      Endpoint::AccountRemove => $this->accountController()->remove($common->request, $segment('id')),
      Endpoint::TokenIssue => $this->tokenController()->issue($common->authorizer->requireAccount(),
        $common->request),
      Endpoint::TokenRuleRemove => $this->tokenController()->remove($common->request, $segment('id'),
        $segment('rule')),
      Endpoint::TokenRevoke => $this->tokenController()->revoke($common->request, $segment('id')),
      Endpoint::LocaleAdd => $common->locales()->add($common->request),
      Endpoint::LocaleOrder => $common->locales()->order($common->request),
      Endpoint::LocaleRemove => $common->locales()->remove($common->request, $segment('tag')),
      Endpoint::Backups => $this->backupController()->show($common->authorizer->requireAccount(),
        $common->requirePerson(), $common->request),
      Endpoint::BackupTake => $this->backupController()->take($common->request),
      Endpoint::BackupRestore => $this->backupController()->restore($common->authorizer->requireAccount(),
        $common->request, $segment('name')),
      Endpoint::BackupDownload => $this->backupController()->download($common->request, $segment('name')),
      Endpoint::Update => $this->updateController()->show($common->authorizer->requireAccount(),
        $common->requirePerson(), $common->request),
      Endpoint::Errors => $this->errorLogController()->show($common->authorizer->requireAccount(),
        $common->requirePerson(), $common->request),
      Endpoint::Checks => $this->checksController()->show($common->authorizer->requireAccount(),
        $common->requirePerson(), $common->request),
    };
  }

  private function updateController(): UpdateController
  {
    $common = $this->common;
    return new UpdateController($common->authorizer, $common->updates(), new Signature(Release::KEY),
      $common->premises->updates, $common->premises->early, $common->frame(), $common->moments, $common->template,
      $common->words);
  }

  private function errorLogController(): ErrorLogController
  {
    $common = $this->common;
    return new ErrorLogController($common->authorizer, $common->errors(), $common->frame(), $common->moments,
      $common->template, $common->words);
  }

  private function checksController(): ChecksController
  {
    $common = $this->common;
    return new ChecksController($common->authorizer, $common->settings, $common->entities, $common->collections,
      $common->named, $common->frame(), $common->moments, $common->template, $common->words);
  }

  private function backupController(): BackupController
  {
    $common = $this->common;
    $box = new Zip($common->premises->data . '/staging/backup-' . $common->uuid->generate() . '.zip',
      $common->clock->now());
    return new BackupController($common->authorizer, $common->backup(), $this->restore(), $box,
      $common->frame(), $common->session, $common->csrf, $common->moments, $common->clock, $common->template,
      $common->words);
  }

  private function restore(): Restore
  {
    $common = $this->common;
    return new Restore($common->backup(), $common->premises->data, new Connection(),
      (new Arrival($common->root, $common->premises, $common->clock, $common->words))->move(), new Admission(),
      $common->clock);
  }

  private function accountController(): AccountController
  {
    $common = $this->common;
    return new AccountController($common->authorizer, $common->accounts, $common->entities,
      $common->validator, $common->profile, $common->settings, $common->uuid,
      $common->clock, $common->csrf, $common->words, $common->db, $common->log, $common->session,
      $common->codes());
  }

  private function tokenController(): TokenController
  {
    $common = $this->common;
    return new TokenController($common->authorizer, $common->grants, $common->tokens, $common->collections,
      $common->entities, $common->keys(), $common->uuid, $common->clock, $common->csrf,
      $common->log);
  }
}
