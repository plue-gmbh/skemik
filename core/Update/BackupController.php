<?php

declare(strict_types=1);

namespace Update;

use Admin\DataLayout;
use Admin\FeedPanel;
use Admin\Frame;
use Admin\Moments;
use Admin\Screen;
use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\Entity;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Http\RouteNotFound;
use Support\Clock;
use Support\Template;
use Support\Words;
use Support\Zip;
use Support\ZipFailed;

/* `/settings/backups`, `runBackup`: the snapshots newest first, each with when
   it was taken, what for, its size and its path, since an operator whose next
   move is to fetch one over SFTP should not have to guess where it went;
   above them whether the daily ones run and what the last daily run left where
   it went wrong. A snapshot taken here is thinned like a daily one, and the
   restore stands behind a dialog saying what one is. The download is the one
   copy off the server a house without SFTP has: a zip that unpacked into
   `backups/` is the snapshot again. From a gigabyte the screen says to use SFTP,
   since a zip the host cut short does not open, its index standing at its end.
   Here beside the two files it drives and not in `core/Admin`. */
final readonly class BackupController
{
  private const LARGE_BYTES = 1_000_000_000;
  private const STALE_SECONDS = 3600;

  public function __construct(
    private Authorizer $authorizer,
    private Backup $backup,
    private Restore $restore,
    private Zip $box,
    private Frame $frame,
    private Session $session,
    private Csrf $csrf,
    private Moments $moments,
    private Clock $clock,
    private Template $template,
    private Words $words,
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::RunBackup);
    $main = $this->template->render('pages/backups', ['words' => $this->words, 'daily' => $this->backup->daily,
      'report' => $this->backup->report(), 'rows' => array_map($this->row(...), $this->backup->list()),
      'notice' => $this->session->take('backups'), 'refusal' => $this->session->take('backups-refused'),
      'csrf' => $this->csrf->token()]);

    return $this->frame->around($account, $person,
      new Screen($this->words->get('backups.title'), $main, FeedPanel::at($request, '/feed')));
  }

  public function take(Request $request): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::RunBackup);
    try {
      $taken = $this->backup->take(Occasion::Manual);
      $this->session->keep('backups', $this->words->get('backups.taken', ['name' => $taken->name]));
    } catch (SnapshotFailed $failure) {
      return $this->refused($this->words->get('backups.refused', ['reason' => $failure->getMessage()]));
    }

    return Response::redirect('/settings/backups');
  }

  /* Under the lock the daily snapshot and the update hold, since either would
     read holdings swapped under it, and the daily take a failure for want of
     room. A refused restore left the house as it was and is said like a refused
     snapshot. The answer carries work after it that does nothing, so neither a
     check nor a daily snapshot runs on the handle this request opened: that is
     the displaced database. */
  public function restore(Account $account, Request $request, string $name): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::RunBackup);
    $snapshot = $this->backup->named($name) ?? throw RouteNotFound::for($request->method, $request->path);
    try {
      $notice = $this->backup->locked(fn () => $this->restore->put($snapshot, $account->id)) ? null
        : $this->words->get('backups.busy');
    } catch (SnapshotFailed $failure) {
      $notice = $this->words->get('backups.unrestored', ['reason' => $failure->getMessage()]);
    }
    if ($notice !== null) {
      return $this->refused($notice);
    }
    $this->session->logOut();

    return Response::redirect('/login')->after(static function (): void {});
  }

  /* The box leaves `staging/` once sent or given up, as an export's does. */
  public function download(Request $request, string $name): Response
  {
    $this->csrf->require($request);
    $this->authorizer->requireHuman(Capability::RunBackup);
    $snapshot = $this->backup->named($name) ?? throw RouteNotFound::for($request->method, $request->path);
    $this->sweep();
    $unfit = $this->unfit($snapshot);
    if ($unfit !== null) {
      return $this->refused($unfit);
    }
    try {
      $this->pack($snapshot);
    } catch (ZipFailed $failure) {
      $this->box->discard();

      return $this->refused($this->words->get('backups.unpacked', ['reason' => $failure->getMessage()]));
    }
    $path = $this->box->path;

    return Response::ranged($path, null, ['Content-Type' => 'application/zip',
      'Content-Disposition' => 'attachment; filename="skemik-' . $snapshot->name . '.zip"'])
      ->after(static function () use ($path): void { is_file($path) && unlink($path); });
  }

  private function refused(string $notice): Response
  {
    $this->session->keep('backups-refused', $notice);

    return Response::redirect('/settings/backups');
  }

  /* Said with the hundred megabytes every screen warns of kept free, so a
     download never makes the house unable to save. */
  private function unfit(Snapshot $snapshot): ?string
  {
    $free = DataLayout::free($this->backup->folder());
    if ($free === null || $free - DataLayout::FREE_BYTES >= $this->backup->size($snapshot)) {
      return null;
    }

    return $this->words->get('backups.unfit', ['free' => (string) intdiv($free, 1_000_000)]);
  }

  /* Under the snapshot's own name, and `media/` written even empty, since a
     restore wants the folder. A house's time limit is lifted, since the box is
     as large as its uploads, and so is a download given up, which would stop PHP
     before the work that removes the box. */
  private function pack(Snapshot $snapshot): void
  {
    function_exists('ignore_user_abort') && ignore_user_abort(true);
    function_exists('set_time_limit') && set_time_limit(0);
    $this->box->add($snapshot->name . '/holdings/media/', '');
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($snapshot->path . '/holdings',
      \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      $this->box->addFile($snapshot->name . substr($file->getPathname(), strlen($snapshot->path)),
        $file->getPathname());
    }
    $this->box->close();
  }

  /* A run PHP stopped half-way left its box behind. */
  private function sweep(): void
  {
    $stale = $this->clock->now()->getTimestamp() - self::STALE_SECONDS;
    foreach (glob(dirname($this->box->path) . '/backup-*.zip') ?: [] as $left) {
      if ((int) filemtime($left) < $stale) {
        unlink($left);
      }
    }
  }

  private function row(Snapshot $snapshot): SnapshotRow
  {
    $bytes = $this->backup->size($snapshot);

    return new SnapshotRow($snapshot, $this->moments->time($snapshot->at), sprintf('%.1f', $bytes / 1_000_000),
      $bytes >= self::LARGE_BYTES);
  }
}
