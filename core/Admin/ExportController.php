<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Session;
use Domain\ActionType;
use Domain\Entity;
use Domain\Props;
use Feed\DumpSize;
use Feed\Export;
use Http\Csrf;
use Http\Request;
use Http\Response;
use Storage\ActivityLog;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\HouseItem;
use Storage\HouseObject;
use Storage\RateLimit;
use Storage\SettingStore;
use Support\Clock;
use Support\Template;
use Support\Uuid;
use Support\Words;
use Support\Zip;
use Support\ZipFailed;

/* `GET /export` and `POST` to take one, `exportHoldings`, from the sidebar's
   foot for every role, since a dump is the reader's own screen serialised
   (`Feed\Export`). The screen says before the run which types come out
   complete and which as the public reads them, because a scoped colleague
   pressing Export has every reason to assume it means all of it, and how large
   the box will be, since it holds the uploads and a house with 4 GB of
   photographs should learn that from the button. It is built into `staging/`
   and streamed, so the data folder has to hold it, which is where a cheap
   webspace's quota is met head-on: a run that cannot fit is refused before it
   writes, and a failed one leaves nothing behind. Backup is a row of the
   Settings card and this is not: Backup is the holdings, Export a reading of
   them, and the manifest keeps the second from being mistaken for the first. */
final readonly class ExportController
{
  /* What four documents of a record weigh, generously, beside the files whose
     bytes are counted: the box of a house without uploads is still not nothing. */
  private const PER_RECORD = 16_000;
  private const STALE_SECONDS = 3600;
  private const LOCK = 'export.lock';

  public function __construct(
    private Authorizer $authorizer,
    private Export $export,
    private Zip $box,
    private string $data,
    private ActivityLog $log,
    private CollectionStore $collections,
    private Sidebar $sidebar,
    private Probes $probes,
    private SettingStore $settings,
    private Session $session,
    private Csrf $csrf,
    private Rows $rows,
    private RateLimit $runs,
    private Uuid $uuid,
    private Clock $clock,
    private Template $template,
    private Words $words,
  ) {}

  public function show(Account $account, Entity $person, Request $request): Response
  {
    $this->authorizer->requireHuman(Capability::ExportHoldings);
    $size = $this->export->measure();
    $entries = $this->sidebar->entries($account);
    $main = $this->template->render('pages/export', ['words' => $this->words, 'size' => $size,
      'about' => Intake::size($this->weight($size)), 'complete' => $this->names($account, $entries, false),
      'public' => $this->names($account, $entries, true), 'unfit' => $this->unfit($size),
      'notice' => $this->session->take('export'), 'csrf' => $this->csrf->token()]);

    return Response::html($this->template->render('partials/frame', ['words' => $this->words,
      'account' => $account, 'title' => $this->words->get('export.title'),
      'menu' => new AccountMenu($account, $person, $this->rows, $this->session),
      'csrf' => $this->csrf->token(), 'entries' => $entries, 'open' => 'export',
      'space' => $this->probes->space(), 'update' => $this->probes->update(),
      'locale' => $this->settings->locales()[0]->tag, 'main' => $main, 'islands' => false,
      'feed' => FeedPanel::at($request, '/feed')]));
  }

  /* The box leaves `staging/` once it has been sent, or the download given up,
     which would otherwise stop PHP before the work after it and leave the box in
     the way of the next run for the hour `sweep` waits; a house's time limit is
     lifted, as for a move, since the box is as large as its uploads. Every role
     may take one and each costs the house a full read of itself, so an account
     starts ten an hour and one box is built at a time. */
  public function take(Entity $person, Request $request): Response
  {
    $this->csrf->require($request);
    $account = $this->authorizer->requireHuman(Capability::ExportHoldings);
    if (!$this->runs->hit('export:' . $account->id)) {
      return $this->refused($this->words->get('export.too_many'));
    }
    function_exists('ignore_user_abort') && ignore_user_abort(true);
    function_exists('set_time_limit') && set_time_limit(0);

    return $this->locked(fn (): Response => $this->built($account, $person));
  }

  /* Held while the box is built and let go before it is sent, since a download
     as slow as its reader holds nothing the next run needs. In the data folder,
     not `staging/`, which an update leaves empty. */
  private function locked(\Closure $build): Response
  {
    $handle = fopen($this->data . '/' . self::LOCK, 'c');
    if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
      return $this->refused($this->words->get('export.busy'));
    }
    try {
      return $build();
    } finally {
      flock($handle, LOCK_UN);
      fclose($handle);
    }
  }

  private function built(Account $account, Entity $person): Response
  {
    $this->sweep();
    $size = $this->export->measure();
    $unfit = $this->unfit($size);
    if ($unfit !== null) {
      return $this->refused($unfit);
    }
    try {
      $written = $this->export->write();
    } catch (ZipFailed $failure) {
      $this->box->discard();

      return $this->refused($this->words->get('export.failed', ['reason' => $failure->getMessage()]));
    }
    $this->record($account, $person, $written, $size);
    $name = 'skemik-export-' . $this->clock->now()->format('Y-m-d') . '.zip';
    $path = $this->box->path;

    return Response::ranged($path, null, ['Content-Type' => 'application/zip',
      'Content-Disposition' => 'attachment; filename="' . $name . '"'])
      ->after(static function () use ($path): void { is_file($path) && unlink($path); });
  }

  private function refused(string $sentence): Response
  {
    $this->session->keep('export', $sentence);

    return Response::redirect('/export');
  }

  /* The collections with something to dump, as the sidebar counts them for this
     reader: those within the read scope, or those outside it as the public
     reads them; one with nothing in it is no line to read.
     @param list<SidebarEntry> $entries
     @return list<string> */
  private function names(Account $account, array $entries, bool $outside): array
  {
    $names = [];
    foreach ($entries as $entry) {
      $held = array_find($this->collections->all(), fn (Collection $each): bool => $each->slug->value === $entry->slug);
      if ($entry->count > 0 && $held !== null && $this->authorizer->readsAsPublic($account, $held->type) === $outside) {
        $names[] = $entry->name;
      }
    }

    return $names;
  }

  /* Said with the hundred megabytes every screen warns of kept free, so a dump
     never makes the house unable to save. */
  private function unfit(DumpSize $size): ?string
  {
    $free = DataLayout::free($this->data);
    if ($free === null || $free - DataLayout::FREE_BYTES >= $this->weight($size)) {
      return null;
    }

    return $this->words->get('export.unfit', ['size' => Intake::size($this->weight($size)),
      'free' => (string) intdiv($free, 1_000_000)]);
  }

  private function weight(DumpSize $size): int
  {
    return $size->bytes + $size->records * self::PER_RECORD;
  }

  /* A run PHP stopped half-way left its box behind, and the next would be told
     it does not fit for good; one being sent this moment is older than that on
     no line a house has. */
  private function sweep(): void
  {
    $stale = $this->clock->now()->getTimestamp() - self::STALE_SECONDS;
    foreach (glob($this->data . '/staging/export-*.zip') ?: [] as $left) {
      if ((int) filemtime($left) < $stale) {
        unlink($left);
      }
    }
  }

  private function record(Account $account, Entity $person, int $written, DumpSize $size): void
  {
    $scope = $account->read->stored();
    $this->log->record(ActionType::Create, new HouseObject(HouseItem::Dump, $this->uuid->generate()),
      new Props(['person' => $person->id->value, 'records' => $written, 'files' => $size->files,
        'formats' => Export::FORMATS, ...($scope === null ? [] : ['scope' => $scope])]));
  }
}
