<?php

declare(strict_types=1);

namespace Update;

use Admin\DataLayout;
use Http\Response;
use Support\Clock;
use Support\Fetch;
use Support\Fetched;
use Support\FetchFailed;

/* Runs by itself, with nobody pressing: once an hour after an answer nobody waits
   for, as the daily snapshot runs, and installs what the check finds at once. The
   migration is not run by this request, whose old classes are loaded while any it
   has not met would load from the new files: the new code is asked over the
   install's own address and moves the holdings as a copied release does. Where
   that failed the tree goes back beside the holdings `Move` put back, and the
   same release is not tried again unless what stopped it was the machine's. Only
   what is true by definition reverts; `Checkup` reports. The release is recorded
   before the swap, so a process the host stops or a move seen late leaves the
   screen right, and the tree aside goes at the next check of the release it made way for. An install that
   declines updates still checks and installs nothing, or its screen would call a stale check current. */
final readonly class Updater
{
  /* The own address gives up after `Fetch`'s seconds while the move runs on: copying, scripts and every record
     rewritten can take minutes for a large house on a webspace. */
  private const WAIT_SECONDS = 900;

  public function __construct(
    private bool $enabled,
    private bool $early,
    private string $data,
    private string $origin,
    private UpdateCheck $check,
    private Downloader $downloader,
    private Signature $signature,
    private Installer $installer,
    private Backup $backup,
    private Fetch $fetch,
    private Clock $clock,
  ) {}

  /* An answer that has work of its own, or streams a file, leaves the check to the next one. */
  public function after(Response $response): Response
  {
    return $response->after === null && $response->file === null && $this->check->due()
      ? $response->after($this->run(...)) : $response;
  }

  public function run(): void
  {
    $this->backup->locked(function (): void {
      if ($this->check->due()) {
        $this->checked();
      }
    });
  }

  private function checked(): void
  {
    [$now, $report] = [$this->clock->now(), $this->check->report()];
    if ($report->installed === Version::APP) {
      $this->installer->settle();
    }
    try {
      $manifest = $this->check->manifest();
    } catch (UpdateRefused $failure) {
      $this->check->record($report->checkedAt($now->format(Clock::STORED), $failure->getMessage(), null));

      return;
    }
    $today = $now->setTimezone($this->clock->zone)->format('Y-m-d');
    $offered = $manifest->offered(Version::APP, $today, $this->early);
    $report = $report->checkedAt($now->format(Clock::STORED), null, $offered ? $manifest->version : null);
    $this->check->record($report);
    if ($this->enabled && $offered && ($report->refused !== $manifest->version || $report->again)) {
      $this->attempted($manifest);
    }
  }

  /* A download that broke off and a snapshot without room are the machine's, as a refusal may be.
     A refusal records over the report from before, the release recorded at the swap having gone back. */
  private function attempted(Manifest $manifest): void
  {
    $before = $this->check->report();
    try {
      $this->installed($manifest);
    } catch (UpdateRefused | FetchFailed | SnapshotFailed $failure) {
      $this->check->record($before->refusing($manifest->version, $failure->getMessage(),
        !$failure instanceof UpdateRefused || $failure->again));
    }
  }

  /* The archive leaves `staging/` whatever happens to it, unpacked or refused. */
  private function installed(Manifest $manifest): void
  {
    $missing = $this->missing();
    if ($missing !== null) {
      throw UpdateRefused::because($missing);
    }
    function_exists('ignore_user_abort') && ignore_user_abort(true);
    function_exists('set_time_limit') && set_time_limit(0);
    [$archive, $minisig] = $this->downloader->fetch($manifest);
    try {
      $trusted = $this->signature->verify($archive, (string) file_get_contents($minisig));
      if (preg_match('/\bversion ' . preg_quote($manifest->version, '/') . '(\s|$)/', $trusted) !== 1) {
        throw UpdateRefused::signature('names another version: ' . $trusted);
      }
      $this->installer->stage($archive);
    } finally {
      $this->downloader->clear();
    }
    $this->taken($manifest);
  }

  /* A staged tree nothing swapped in leaves the domain root with the refusal,
     where it would stand for as long as the data folder is short of room. */
  private function taken(Manifest $manifest): void
  {
    try {
      $this->swapped($manifest);
    } catch (\Throwable $failure) {
      $this->installer->discard();
      throw $failure;
    }
  }

  private function missing(): ?string
  {
    $needed = ['sodium', 'phar', ...(str_starts_with(Release::ADDRESS, 'https://') ? ['openssl'] : [])];
    foreach ($needed as $extension) {
      if (!extension_loaded($extension)) {
        return 'the PHP extension ' . $extension . ' is missing';
      }
    }

    return match (true) {
      !$this->installer->writable() => 'PHP cannot write into the folder holding index.php',
      (DataLayout::free($this->data) ?? DataLayout::FREE_BYTES) < DataLayout::FREE_BYTES
        => 'fewer than ' . intdiv(DataLayout::FREE_BYTES, 1_000_000) . ' MB are free where the data lies',
      default => null,
    };
  }

  /* The classes met after the swap are loaded before it, since one met later would load from the new tree.
     A failed swap has put the tree back itself, and a failed snapshot moved nothing to put back. */
  private function swapped(Manifest $manifest): void
  {
    array_map(class_exists(...), [SnapshotFailed::class, FetchFailed::class, Fetched::class, UpdateRefused::class]);
    $marker = fopen($this->data . '/' . Move::MARKER, 'c');
    if ($marker === false || !flock($marker, LOCK_EX | LOCK_NB)) {
      throw UpdateRefused::because('the holdings are being moved by another request');
    }
    try {
      ftruncate($marker, 0);
      fwrite($marker, $manifest->version . "\n");
      $this->backup->take(Occasion::Update);
      $this->check->record($this->check->report()->installing($manifest->version,
        $this->clock->now()->format(Clock::STORED), $manifest->security));
      $this->installer->swap();
    } catch (\Throwable $failure) {
      unlink($this->data . '/' . Move::MARKER);
      throw $failure;
    } finally {
      flock($marker, LOCK_UN);
      fclose($marker);
    }
    $this->moved($manifest->version);
  }

  /* Any request moves the holdings, so one is asked over the install's own
     address, and what it leaves is read: the stamp, or the marker naming why.
     Neither seen in time leaves the move to the new code, whose halt names a failure. */
  private function moved(string $version): void
  {
    try {
      $this->fetch->get($this->origin . '/robots.txt');
    } catch (FetchFailed) {
    }
    $deadline = time() + self::WAIT_SECONDS;
    while (time() < $deadline) {
      clearstatcache();
      if (Move::stamped($this->data, $version)) {
        $this->installer->settle();

        return;
      }
      $reason = Move::left($this->data, $version);
      if ($reason !== null) {
        $this->installer->revert();
        unlink($this->data . '/' . Move::MARKER);
        throw UpdateRefused::release('the holdings could not be moved: ' . $reason);
      }
      usleep(250_000);
    }
  }
}
