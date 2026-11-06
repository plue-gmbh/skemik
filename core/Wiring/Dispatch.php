<?php

declare(strict_types=1);

namespace Wiring;

use Http\Endpoint;
use Http\Response;
use Http\Route;
use Storage\HoldingsCheck;
use Storage\Schema;
use Update\Checkup;
use Update\Daily;

/* Which area answers an endpoint, as `Router`'s table says which endpoint a URL
   is: reading it tells you where every route's controller is built. The first
   run is not here, since `index.php` answers it before a `Common` can exist. */
final readonly class Dispatch
{
  public function __construct(private Common $common) {}

  /* An answer carries one piece of work after it, so the first hook that is due takes it and the rest wait for a
     later answer: the day's snapshot, then the release, then the release's files, each due once a day, and the
     slice fills in behind them. */
  public function answer(Route $route): Response
  {
    $answer = $this->area($route);
    $answer = $this->daily()->after($answer);
    $answer = $this->common->updater()->after($answer);
    $answer = $this->common->files()->after($answer);

    return $this->checkup()->after($answer);
  }

  private function area(Route $route): Response
  {
    return match ($route->endpoint) {
      Endpoint::LoginForm, Endpoint::LoginAsk, Endpoint::LoginRedeem, Endpoint::Logout
        => (new Login($this->common))->answer($route),
      Endpoint::Home, Endpoint::Find, Endpoint::Activity, Endpoint::ActivityRedact, Endpoint::Export,
        Endpoint::ExportTake => (new Home($this->common))->answer($route),
      Endpoint::Collection, Endpoint::Search, Endpoint::Record, Endpoint::RecordCreate,
        Endpoint::RecordPatch, Endpoint::RecordTranslation, Endpoint::RecordDuplicate, Endpoint::RecordLink,
        Endpoint::RecordUnlink => (new Records($this->common))->records($route),
      Endpoint::Trash, Endpoint::RecordDelete, Endpoint::RecordRestore, Endpoint::RecordPurge,
        Endpoint::RecordsDelete, Endpoint::RecordsRestore, Endpoint::RecordsPurge
        => (new Records($this->common))->trash($route),
      Endpoint::FieldAdd, Endpoint::FieldEdit, Endpoint::FieldRemove
        => (new Records($this->common))->fields($route),
      Endpoint::MediaUpload, Endpoint::MediaReplace, Endpoint::MediaFile, Endpoint::MediaThumb
        => (new Records($this->common))->media($route),
      Endpoint::AccountCreate, Endpoint::AccountEdit, Endpoint::AccountRemove, Endpoint::TokenIssue,
        Endpoint::TokenRuleRemove, Endpoint::TokenRevoke, Endpoint::LocaleAdd, Endpoint::LocaleOrder,
        Endpoint::LocaleRemove, Endpoint::General, Endpoint::GeneralSave, Endpoint::Backups,
        Endpoint::BackupTake, Endpoint::BackupRestore, Endpoint::BackupDownload, Endpoint::BackupDaily,
        Endpoint::Update, Endpoint::UpdateCheck, Endpoint::UpdateMode, Endpoint::Errors, Endpoint::Checks
        => (new Settings($this->common))->answer($route),
      Endpoint::FeedCatalog, Endpoint::FeedLocale, Endpoint::FeedProfile, Endpoint::FeedCollection,
        Endpoint::FeedRecord, Endpoint::IngestCreate, Endpoint::IngestPatch, Endpoint::IngestArrange,
        Endpoint::FeedPreflight, Endpoint::Robots, Endpoint::SecurityText, Endpoint::PanelCatalog,
        Endpoint::PanelLocale, Endpoint::PanelCollection, Endpoint::PanelRecord
        => (new Address($this->common))->answer($route),
    };
  }

  private function daily(): Daily
  {
    return new Daily($this->common->backup(), $this->common->clock);
  }

  private function checkup(): Checkup
  {
    $common = $this->common;
    return new Checkup(new HoldingsCheck($common->db, $common->profile, new Schema($common->profile),
      $common->validator, $common->entities, $common->relations, $common->collisions, $common->settings,
      $common->search), $common->settings, $common->clock, $common->backup());
  }
}
