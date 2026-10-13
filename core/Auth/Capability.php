<?php

declare(strict_types=1);

namespace Auth;

/* What can be attempted. Controllers ask for a capability, never for a role:
   `if role === owner` scattered through controllers is how permission bugs happen.
   The destructive and the disclosing cases stay apart although one role holds
   them all today, because one `manageEverything` could never later be given to
   anybody narrower. `exportHoldings` is not folded into `readContent` so a house
   can withhold the bulk motion without withholding reading. There is deliberately
   no `publishContent`: releasing is folded into writing, and a case for it would
   be the seam a four-eyes feature grows out of by accident. `readLog` reads the
   log, who did what, and the error log beside it, both the house's own history
   that no colleague needs to do their work. */
enum Capability: string
{
  case ReadContent = 'readContent';
  case WriteContent = 'writeContent';
  case ManageFields = 'manageFields';
  case ManageAccounts = 'manageAccounts';
  case ManageTokens = 'manageTokens';
  case ManageHouse = 'manageHouse';
  case RunUpdate = 'runUpdate';
  case RunBackup = 'runBackup';
  case EraseData = 'eraseData';
  case ReadLog = 'readLog';
  case ExportHoldings = 'exportHoldings';

  /* The two a type scope narrows; every other case is about the house, not a type. */
  public function isAboutContent(): bool
  {
    return $this === self::ReadContent || $this === self::WriteContent;
  }
}
