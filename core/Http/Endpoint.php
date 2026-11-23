<?php

declare(strict_types=1);

namespace Http;

/* What a matched route asks for: `Router` names it, `core/Wiring` builds the
   controller that answers it. A case per route rather than a controller class
   name in the table, so no string is ever turned into a class. */
enum Endpoint
{
  case Home;
  case Install;
  case InstallRun;
  case LoginForm;
  case LoginAsk;
  case LoginRedeem;
  case Logout;
  case Collection;
  case Search;
  case Find;
  case Record;
  case RecordCreate;
  case RecordPatch;
  case RecordDelete;
  case RecordRestore;
  case RecordsDelete;
  case RecordsRestore;
  case RecordPurge;
  case RecordsPurge;
  case Trash;
  case RecordTranslation;
  case RecordDuplicate;
  case RecordLink;
  case RecordUnlink;
  case FieldAdd;
  case FieldEdit;
  case FieldRemove;
  case MediaUpload;
  case MediaReplace;
  case MediaFile;
  case MediaThumb;
  case AccountCreate;
  case AccountEdit;
  case AccountRemove;
  case TokenIssue;
  case TokenRuleRemove;
  case TokenRevoke;
  case LocaleAdd;
  case LocaleOrder;
  case LocaleRemove;
  case QuestionSave;
  case QuestionRemove;
  case Activity;
  case ActivityRedact;
  case General;
  case GeneralSave;
  case Backups;
  case BackupTake;
  case BackupRestore;
  case BackupDownload;
  case BackupDaily;
  case Update;
  case UpdateCheck;
  case UpdateMode;
  case Errors;
  case Checks;
  case Export;
  case ExportTake;
  case FeedCatalog;
  case FeedLocale;
  case FeedProfile;
  case FeedCollection;
  case FeedRecord;
  case IngestCreate;
  case IngestPatch;
  case IngestArrange;
  case FeedPreflight;
  case Robots;
  case SecurityText;
  case WebManifest;
  case PanelCatalog;
  case PanelLocale;
  case PanelCollection;
  case PanelRecord;

  /* Which door a route is: the address answers a machine, so no read of it
     starts a session or sends a cookie back — a `Set-Cookie` on a response a
     shared cache may hold is the one way an anonymous reading could leak. */
  public function isMachine(): bool
  {
    return in_array($this, [self::FeedCatalog, self::FeedLocale, self::FeedProfile, self::FeedCollection,
      self::FeedRecord, self::IngestCreate, self::IngestPatch, self::IngestArrange, self::FeedPreflight,
      self::Robots, self::SecurityText, self::WebManifest], true);
  }

  /* A file's answer and a search's read who asks and write nothing: the session
     resumed is let go before either is answered, since a screen's thumbnails would
     otherwise queue on its lock one by one, each holding a worker meanwhile, and a
     search that takes long would hold every other screen of its colleague. */
  public function onlyReadsSession(): bool
  {
    return in_array($this, [self::MediaFile, self::MediaThumb, self::Search], true);
  }

  /* The first run starts its own session once it has settled the data folder,
     since until then there is nowhere to keep one. */
  public function isFirstRun(): bool
  {
    return $this === self::Install || $this === self::InstallRun;
  }
}
