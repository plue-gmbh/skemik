<?php

declare(strict_types=1);

namespace Admin;

use Support\Fetch;
use Support\Fetched;
use Support\FetchFailed;
use Support\Words;

/* Where the holdings live, named by the operator and proven rather than assumed.
   The folder is theirs: made beforehand, taken only while empty, never
   created or removed here, so a mistyped path can reach nothing that holds
   something. Inside the domain root it is accepted only when a fetch of the
   marker from outside answers 403 or 404. The deny rules go into it wherever it
   is: hosts that nest one domain's root inside another's make a folder above one
   domain somebody else's served one. `config.php` is proven the same way, since a
   host where PHP quietly stops running serves it as text; one that leaks is
   removed with what was made inside the folder, so the next run starts clean. */
final readonly class DataLayout
{
  private const FOLDERS = ['holdings', 'sessions', 'login', 'log'];
  private const EXTENSIONS = ['pdo_sqlite', 'intl', 'dom', 'mbstring'];
  public const FREE_BYTES = 100_000_000;
  private const MARKER = 'unreachable.txt';
  private const SQLITE = '3.38';
  private const MARKER_TEXT = "Skemik fetches this file from outside to prove the folder is not served.\n";

  public function __construct(private string $root, private Fetch $fetch, private Words $words) {}

  /** @return list<Requirement> */
  public function requirements(?Premises $configured): array
  {
    [$free, $writable] = [self::free($configured?->data ?? $this->root), is_writable($this->root)];

    return [
      new Requirement($this->words->get('install.php', ['found' => PHP_VERSION]), PHP_VERSION_ID >= 80500),
      ...array_map(fn (string $extension): Requirement => new Requirement(
        $this->words->get('install.extension', ['extension' => $extension]), extension_loaded($extension)),
        self::EXTENSIONS),
      ...$this->sqlite(),
      new Requirement($writable ? $this->words->get('install.root') : $this->writing('install.root', []), $writable),
      ...($configured === null ? [] : [new Requirement($this->words->get('install.configured'),
        is_writable($configured->data))]),
      new Requirement($this->words->get('install.space',
        ['free' => $free === null ? '–' : (string) intdiv($free, 1_000_000)]),
        ($free ?? self::FREE_BYTES) >= self::FREE_BYTES),
      ...$this->workers(),
    ];
  }

  /* The bytes free where a folder lies, null where the host does not say, the
     function disabled or a quota it cannot read: no answer is no shortage, or a
     house with room is refused its install and every update. */
  public static function free(string $folder): ?int
  {
    $free = function_exists('disk_free_space') ? disk_free_space($folder) : false;

    return $free === false ? null : (int) $free;
  }

  /* What costs and refuses nothing, said before somebody measures it: without
     the opcode cache PHP compiles the tree anew per request, with
     `zend.assertions` left at 1 the self-tests run on every page, and run as
     CGI or FastCGI PHP cannot end an answer before the work after it, so a
     login waits for its mail and its time tells whether the address has an account.
     @return list<Requirement> */
  public function costs(): array
  {
    $enabled = PHP_SAPI === 'cli-server' ? 'opcache.enable_cli' : 'opcache.enable';
    $cached = extension_loaded('Zend OPcache') && filter_var(ini_get($enabled), FILTER_VALIDATE_BOOL);
    $assertions = (string) ini_get('zend.assertions');

    return [
      new Requirement($this->words->get('install.opcache'), $cached),
      new Requirement($this->words->get('install.assertions', ['found' => $assertions]), $assertions === '-1'),
      new Requirement($this->words->get('install.finish'),
        function_exists('fastcgi_finish_request') || PHP_SAPI !== 'cgi-fcgi'),
    ];
  }

  /* The named folder, checked, furnished and proven; the database is not touched. */
  public function settle(string $origin, string $typed): string
  {
    $unmet = array_filter($this->requirements(null), fn (Requirement $requirement): bool => !$requirement->met);
    if ($unmet !== []) {
      throw InstallRefused::because($this->words->get('install.refused.unmet'));
    }
    $data = $this->folder($typed);
    $this->furnish($data);
    $served = $this->servedPath($data);
    if ($served !== null) {
      $this->proveRefused($origin . $served . '/' . self::MARKER, $data);
    }

    return $data;
  }

  /* Written under a `.php` name and renamed, so no half-written file is ever
     read and the draft beside it is never served as text, and readable by PHP
     alone before it has its name, since a relay's password is typed into it.
     The address is the one this run is proven under, kept for what the install
     says outside a request; every other key stands at what its absence means,
     so an operator fills one in rather than looks up its name. */
  public function writeConfig(Premises $premises): void
  {
    $path = $this->root . '/config.php';
    if (file_exists($path)) {
      throw InstallRefused::because($this->words->get('install.refused.config_taken'));
    }
    $sentinel = bin2hex(random_bytes(16));
    $draft = $this->root . '/config-' . bin2hex(random_bytes(8)) . '.php';
    file_put_contents($draft, "<?php\n\nreturn " . var_export(['data' => $premises->data,
      'address' => $premises->address, 'sentinel' => $sentinel,
      'relay' => ['host' => '', 'port' => 587, 'user' => '', 'password' => '', 'sender' => ''],
      'daily_backups' => $premises->dailyBackups, 'updates' => $premises->updates,
      'security_contact' => $premises->securityContact], true) . ";\n");
    chmod($draft, 0600);
    rename($draft, $path);
    $this->proveHidden($premises->address . '/config.php', $sentinel, $premises->data);
  }

  /* The library `pdo_sqlite` was built with, asked of it: `strict` tables need
     3.37, `format()` and the JSON functions 3.38. Without the extension the line
     is unmet beside its own, naming what a hoster is asked for.
     @return list<Requirement> */
  private function sqlite(): array
  {
    $version = extension_loaded('pdo_sqlite')
      ? (string) (new \PDO('sqlite::memory:'))->query('select sqlite_version()')->fetchColumn() : '';

    return [new Requirement($this->words->get('install.sqlite', ['found' => $version === '' ? '–' : $version]),
      version_compare($version, self::SQLITE, '>='))];
  }

  /* PHP's own server answers one request at a time unless told otherwise, and
     the proofs are a second request made while the first waits for it.
     @return list<Requirement> */
  private function workers(): array
  {
    if (PHP_SAPI !== 'cli-server') {
      return [];
    }

    return [new Requirement($this->words->get('install.workers'),
      (int) getenv('PHP_CLI_SERVER_WORKERS') >= 2)];
  }

  /* Relative to the domain root where not absolute, and named in a refusal as
     typed, since resolved it would tell anyone before the first run where the
     domain root lies. `open_basedir` is asked first, since a path outside it
     prints a warning where it should refuse. */
  private function folder(string $typed): string
  {
    $typed = trim($typed);
    $path = $this->normalised(str_starts_with($typed, '/') ? $typed : $this->root . '/' . $typed);
    $refused = fn (string $key): InstallRefused => InstallRefused::because(
      $this->words->get($key, ['folder' => $typed]));

    return match (true) {
      $typed === '' => throw InstallRefused::because($this->words->get('install.refused.folder_empty')),
      !$this->reachable($path) => throw $refused('install.refused.folder_basedir'),
      !is_dir($path) => throw $refused('install.refused.folder_missing'),
      (scandir($path) ?: []) !== ['.', '..'] => throw $refused('install.refused.folder_used'),
      !is_writable($path) => throw InstallRefused::because(
        $this->writing('install.refused.folder_unwritable', ['folder' => $typed])),
      default => (string) realpath($path),
    };
  }

  /* A line about a write PHP was refused, naming the user it runs as where the
     host lets that be asked, since the write is mended for that account, and only
     then, since the page shows it to anyone before the first run. Never the owner
     of the files, which PHP answers without `posix`: on the host that meets this
     line it is the wrong one.
     @param array<string, string> $values */
  private function writing(string $key, array $values): string
  {
    $account = function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid()) : false;

    return $account === false ? $this->words->get($key, $values)
      : $this->words->get($key . '_as', [...$values, 'user' => $account['name']]);
  }

  /* `.` and `..` resolved by hand, since `realpath` answers nothing for a path
     that may not be asked about. */
  private function normalised(string $path): string
  {
    $segments = [];
    foreach (explode('/', $path) as $segment) {
      match ($segment) {
        '', '.' => null,
        '..' => array_pop($segments),
        default => $segments[] = $segment,
      };
    }

    return '/' . implode('/', $segments);
  }

  private function reachable(string $path): bool
  {
    $bases = array_filter(explode(PATH_SEPARATOR, (string) ini_get('open_basedir')));

    return $bases === [] || array_filter($bases,
      fn (string $base): bool => str_starts_with($path . '/', rtrim($base, '/') . '/')) !== [];
  }

  /* The address a folder inside the domain root answers at, null for one outside it. */
  public function servedPath(string $data): ?string
  {
    $root = (string) realpath($this->root);
    if (!str_starts_with($data, $root . '/')) {
      return null;
    }

    return implode('/', array_map(rawurlencode(...), explode('/', substr($data, strlen($root)))));
  }

  /* `sessions/` is checked rather than trusted to its mode, since a file system
     that ignores modes shows every other account on the box the owner's session. */
  private function furnish(string $data): void
  {
    foreach (self::FOLDERS as $folder) {
      mkdir($data . '/' . $folder, 0700);
    }
    file_put_contents($data . '/.htaccess', "Require all denied\n");
    file_put_contents($data . '/' . self::MARKER, self::MARKER_TEXT);
    clearstatcache();
    if ((fileperms($data . '/sessions') & 0077) !== 0) {
      $this->unfurnish($data);
      throw InstallRefused::because($this->words->get('install.refused.sessions'));
    }
  }

  private function proveRefused(string $url, string $data): void
  {
    try {
      $status = $this->fetched($url)->status;
      if ($status !== 403 && $status !== 404) {
        throw InstallRefused::because($this->words->get($status === 200 ? 'install.refused.served'
          : 'install.refused.unproven', ['status' => (string) $status]));
      }
    } catch (InstallRefused $refused) {
      $this->unfurnish($data);
      throw $refused;
    }
  }

  private function proveHidden(string $url, string $sentinel, string $data): void
  {
    try {
      if (str_contains($this->fetched($url)->body, $sentinel)) {
        throw InstallRefused::because($this->words->get('install.refused.config_served'));
      }
    } catch (InstallRefused $refused) {
      unlink($this->root . '/config.php');
      $this->unfurnish($data);
      throw $refused;
    }
  }

  private function fetched(string $url): Fetched
  {
    try {
      return $this->fetch->get($url);
    } catch (FetchFailed $failure) {
      throw InstallRefused::because($this->words->get('install.refused.unreachable',
        ['reason' => $failure->getMessage()]));
    }
  }

  /* What `furnish` made, and nothing else: the folder stays the operator's. */
  private function unfurnish(string $data): void
  {
    foreach (self::FOLDERS as $folder) {
      rmdir($data . '/' . $folder);
    }
    unlink($data . '/.htaccess');
    unlink($data . '/' . self::MARKER);
  }
}
