<?php

declare(strict_types=1);

namespace Wiring;

use Admin\ActivityController;
use Admin\Changes;
use Admin\Colleagues;
use Admin\ExportController;
use Admin\HomeController;
use Admin\HouseChanges;
use Admin\NeedsLook;
use Admin\Register;
use Auth\Account;
use Feed\Export;
use Feed\PublicFilter;
use Http\Endpoint;
use Http\Response;
use Http\Route;
use Storage\Oversights;
use Support\Zip;

/* The home screen, whose cards show the accounts, keys and languages `Settings`
   writes, the whole log its Last changed opens, and the export the sidebar's
   foot opens on every screen. */
final readonly class Home
{
  public function __construct(private Common $common) {}

  public function answer(Route $route): Response
  {
    [$common, $account] = [$this->common, $this->common->authorizer->requireAccount(...)];
    return match ($route->endpoint) {
      Endpoint::Activity => $this->activity()->show($account(), $common->requirePerson(), $common->request),
      Endpoint::ActivityRedact => $this->activity()->redact($common->request, $route->parameter('id')),
      Endpoint::Home => $this->controller()->show($account(), $common->requirePerson(), $common->request),
      Endpoint::Export => $this->export($account())->show($account(), $common->requirePerson(), $common->request),
      Endpoint::ExportTake => $this->export($account())->take($common->requirePerson(), $common->request),
    };
  }

  private function export(Account $account): ExportController
  {
    $common = $this->common;
    $box = new Zip($common->premises->data . '/staging/export-' . $common->uuid->generate() . '.zip',
      $common->clock->now());
    return new ExportController($common->authorizer, $this->dump($account, $box), $box, $common->premises->data,
      $common->log, $common->collections, $common->frame(), $common->sidebar, $common->session, $common->csrf,
      $common->limit(10, 3600), $common->uuid, $common->clock, $common->template, $common->words);
  }

  /* The dump is the reader's own screen written to disk, so its filter is the
     account's read scope, as the library's door builds it. */
  private function dump(Account $account, Zip $box): Export
  {
    $common = $this->common;
    return new Export($common->profile, $common->schema, $common->entities, $common->relations,
      $common->collections, $common->settings, new PublicFilter($common->profile, $common->clock, null, $account->read),
      $common->authorizer, $account, $common->request->origin(), $common->premises->data . '/holdings/media',
      (string) $common->requirePerson()->props->string('name'), $box, $common->clock, $common->terms,
      $common->words);
  }

  private function activity(): ActivityController
  {
    $common = $this->common;
    return new ActivityController($common->authorizer, $common->log, $this->changes(), $common->collections,
      $common->frame(), $common->session, $common->csrf, $common->template, $common->words);
  }

  private function controller(): HomeController
  {
    $common = $this->common;
    return new HomeController($common->frame(), $common->sidebar, $common->settings, $common->csrf,
      $common->template, $common->words, $common->moments, $common->session, $this->needsLook(), $this->changes(),
      new Colleagues($common->accounts, $common->entities, $common->profile, $common->filter, $common->rows,
        $common->moments, $common->words),
      new Register($common->reviews(), $common->keys(), $common->grants, $common->collections, $common->facts(),
        $common->words), $common->locales(), $common->backup(),
      $common->files(), $common->errors(), $common->relay, $common->premises);
  }

  private function needsLook(): NeedsLook
  {
    $common = $this->common;
    return new NeedsLook($common->settings, $common->entities, $common->relations, $common->collections,
      $common->validator, $common->authorizer, new Oversights($common->db),
      $common->grants, $common->profile, $common->backup(), $common->files(), $common->updates(),
      $common->clock);
  }

  private function changes(): Changes
  {
    $common = $this->common;
    return new Changes($common->log, $common->entities, $common->tokens, $common->collections,
      $common->sheet(), $common->rows, new HouseChanges($common->entities, $common->tokens, $common->collections,
        $common->named, $common->words), $common->clock, $common->moments, $common->named, $common->words);
  }
}
