<?php

declare(strict_types=1);

namespace Wiring;

use Http\Endpoint;
use Http\Response;
use Http\Route;
use Storage\HoldingsCheck;
use Storage\Schema;
use Support\Fetch;
use Update\Checkup;
use Update\Daily;
use Update\Downloader;
use Update\Installer;
use Update\Release;
use Update\Signature;
use Update\Updater;

/* Which area answers an endpoint, as `Router`'s table says which endpoint a URL
   is: reading it tells you where every route's controller is built. The first
   run is not here, since `index.php` answers it before a `Common` can exist. */
final readonly class Dispatch
{
  public function __construct(private Common $common) {}

  public function answer(Route $route): Response
  {
    $records = new Records($this->common);
    return $this->checkup()->after($this->updater()->after($this->daily()->after(match ($route->endpoint) {
      Endpoint::LoginForm, Endpoint::LoginAsk, Endpoint::LoginRedeem, Endpoint::Logout
        => (new Login($this->common))->answer($route),
      Endpoint::Home, Endpoint::Activity, Endpoint::ActivityRedact, Endpoint::Export, Endpoint::ExportTake
        => (new Home($this->common))->answer($route),
      Endpoint::Collection, Endpoint::Search, Endpoint::Preview, Endpoint::Record, Endpoint::RecordCreate,
        Endpoint::RecordPatch, Endpoint::RecordTranslation, Endpoint::RecordLink, Endpoint::RecordUnlink
        => $records->records($route),
      Endpoint::Trash, Endpoint::RecordDelete, Endpoint::RecordRestore, Endpoint::RecordPurge,
        Endpoint::RecordsDelete, Endpoint::RecordsRestore, Endpoint::RecordsPurge => $records->trash($route),
      Endpoint::FieldAdd, Endpoint::FieldEdit, Endpoint::FieldRemove => $records->fields($route),
      Endpoint::MediaUpload, Endpoint::MediaReplace, Endpoint::MediaFile, Endpoint::MediaThumb
        => $records->media($route),
      Endpoint::AccountCreate, Endpoint::AccountEdit, Endpoint::AccountRemove, Endpoint::TokenIssue,
        Endpoint::TokenRuleRemove, Endpoint::TokenRevoke, Endpoint::LocaleAdd, Endpoint::LocaleOrder,
        Endpoint::LocaleRemove, Endpoint::Backups, Endpoint::BackupTake, Endpoint::BackupRestore,
        Endpoint::BackupDownload, Endpoint::Update, Endpoint::Errors, Endpoint::Checks
        => (new Settings($this->common))->answer($route),
      Endpoint::FeedCatalog, Endpoint::FeedLocale, Endpoint::FeedProfile, Endpoint::FeedCollection,
        Endpoint::FeedRecord, Endpoint::IngestCreate, Endpoint::IngestPatch, Endpoint::IngestArrange,
        Endpoint::FeedPreflight, Endpoint::Robots, Endpoint::SecurityText, Endpoint::PanelLocale,
        Endpoint::PanelCollection, Endpoint::PanelRecord => (new Address($this->common))->answer($route),
    })));
  }

  /* The day's snapshot first, then the release, each due once a day, and the slice fills in behind them. */
  private function daily(): Daily
  {
    return new Daily($this->common->backup(), $this->common->clock);
  }

  private function updater(): Updater
  {
    $common = $this->common;
    return new Updater($common->premises->updates, $common->premises->early, $common->premises->data,
      $common->premises->address, $common->updates(), new Downloader($common->premises->data . '/staging', new Fetch()),
      new Signature(Release::KEY), new Installer($common->root, $common->backup()), $common->backup(), new Fetch(),
      $common->clock);
  }

  private function checkup(): Checkup
  {
    $common = $this->common;
    return new Checkup(new HoldingsCheck($common->db, $common->profile, new Schema($common->profile),
      $common->validator, $common->entities, $common->relations, $common->collisions, $common->settings,
      $common->search), $common->settings, $common->clock, $common->backup());
  }
}
