<?php

declare(strict_types=1);

namespace Wiring;

use Admin\Arrivals;
use Admin\Notices;
use Auth\Mailer;
use Storage\Oversights;
use Support\Fetch;
use Update\Downloader;
use Update\Installer;
use Update\Release;
use Update\Signature;
use Update\Updater;

/* What runs once an answer has left and is started from more than one place: the
   update, after any answer and on the update screen's asking, and the mails that
   tell of what came, after a key's write, any answer once a line's hour is out,
   an update and a logged failure, the last from `index.php`. Apart from `Common`, which builds what answers. */
final readonly class Upkeep
{
  public function __construct(private Common $common) {}

  public function updater(): Updater
  {
    $common = $this->common;
    return new Updater($common->premises->updates, $common->premises->early, $common->premises->data,
      $common->premises->address, $common->updates(),
      new Downloader($common->premises->data . '/staging', new Fetch()), new Signature(Release::KEY),
      new Installer($common->root, $common->backup()), $common->backup(), $this->notices(), new Fetch(),
      $common->clock);
  }

  public function notices(): Notices
  {
    $common = $this->common;
    return new Notices($common->accounts, $common->entities, $common->authorizer, $common->collections,
      $common->settings, $common->errors(), new Oversights($common->db), new Arrivals($common->premises->data),
      new Mailer($common->relay), $common->template, $common->root . '/language', $common->premises->address);
  }
}
