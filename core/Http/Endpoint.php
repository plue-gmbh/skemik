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
  case Activity;
  case ActivityRedact;
  case Backups;
  case BackupTake;
  case BackupRestore;
  case BackupDownload;
  case Update;
  case UpdateCheck;
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
      self::Robots, self::SecurityText], true);
  }

  /* A file's answer reads who asks and writes nothing: the session it resumed
     is let go before the file is read, since a screen's thumbnails would otherwise
     queue on its lock one by one, each holding a worker meanwhile. */
  public function isFile(): bool
  {
    return $this === self::MediaFile || $this === self::MediaThumb;
  }

  /* The first run starts its own session once it has settled the data folder,
     since until then there is nowhere to keep one. */
  public function isFirstRun(): bool
  {
    return $this === self::Install || $this === self::InstallRun;
  }
}
