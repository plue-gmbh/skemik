<?php

declare(strict_types=1);

namespace Admin;

/* The findings of Needs a look (`NeedsLook`), in the order the card lists them. */
enum Overlooked: string
{
  case Unread = 'unread';
  case Orders = 'orders';
  case Reservations = 'reservations';
  case Draft = 'draft';
  case Review = 'review';
  case Required = 'required';
  case NoAlt = 'no_alt';
  case Dangling = 'dangling';
  case Reported = 'reported';
  case BackupFailed = 'backup_failed';
  case BackupShrunk = 'backup_shrunk';
  case Update = 'update';
  case Expiring = 'expiring';
}
