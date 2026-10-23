<?php

declare(strict_types=1);

namespace Storage;

use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Violation;
use Support\Clock;
use Support\Json;

/* Reads and writes `setting` through typed accessors only, so the key set is
   closed by the absence of any other method and nothing downstream reads a raw
   string out of a key-value table. It is not a config store: a table that takes
   an arbitrary key grows a settings screen nobody can reason about.
   Which store a switch lives in is the spine's copy test moved up a level: what
   travels with the holdings is a row here (the timezone, the default chrome
   language, the locale list); what changes with the machine is `config.php`'s.
   An unset key answers the default an install starts from. A locale that still
   holds rows or words is refused here and not only greyed out on the screen,
   because removing it would leave records no route reaches and words no route
   reads; trashed ones count, since the trash gives them back. */
final readonly class SettingStore
{
  public const DEFAULT_TIMEZONE = 'Europe/Berlin';
  private const TIMEZONE = 'timezone';
  private const CHROME_LOCALE = 'chrome_locale';
  private const LOCALES = 'locales';
  private const HOLDINGS_CURSOR = 'holdings_cursor';
  private const HOLDINGS_REPORT = 'holdings_report';

  public function __construct(
    private \PDO $db,
    private Clock $clock,
    private Json $json = new Json(),
  ) {}

  public function timezone(): \DateTimeZone
  {
    return new \DateTimeZone((string) ($this->read(self::TIMEZONE) ?? self::DEFAULT_TIMEZONE));
  }

  public function setTimezone(\DateTimeZone $zone): void
  {
    $this->write(self::TIMEZONE, $this->json->encode($zone->getName()));
  }

  /* Null leaves the choice to the browser's languages. */
  public function chromeLocale(): ?Locale
  {
    $tag = $this->read(self::CHROME_LOCALE);

    return $tag === null ? null : new Locale((string) $tag);
  }

  public function setChromeLocale(?Locale $locale): void
  {
    $this->write(self::CHROME_LOCALE, $this->json->encode($locale?->tag));
  }

  /** @return list<Locale> the first is the default a route carries when it names none */
  public function locales(): array
  {
    $tags = $this->read(self::LOCALES);
    $list = $tags === null ? ['de-DE'] : $this->json->decode('{"tags":' . $tags . '}')['tags'];

    return array_map(fn (string $tag): Locale => new Locale($tag), $list);
  }

  /** @param list<Locale> $locales */
  public function setLocales(array $locales): void
  {
    $tags = array_map(fn (Locale $locale): string => $locale->tag, $locales);
    if ($tags === [] || count(array_unique($tags)) !== count($tags)) {
      throw MalformedValue::named('locale list', implode(', ', $tags));
    }
    $left = array_diff(array_keys($this->held()), $tags);
    if ($left !== []) {
      throw MalformedValue::named('locale list, since records or words remain in ' . reset($left),
        implode(', ', $tags));
    }
    $this->write(self::LOCALES, $this->json->encode($tags));
  }

  /* Every tag something is written in: a record's own, the words a record or an
     edge keeps under `l10n`, in the trash too. Each with the records outside the
     trash written in it, one record counted once, for the Languages card: one
     pass, since each walks every record's words.
     @return array<string, int> by tag */
  public function held(): array
  {
    $statement = $this->db->prepare('select tag, count(distinct case when live then id end) from ('
      . 'select id, locale as tag, deleted_at is null as live from entity'
      . " union all select e.id, l.key, e.deleted_at is null from entity e, json_each(e.props, '$.l10n') l"
      . " union all select null, l.key, 0 from relation r, json_each(r.props, '$.l10n') l) group by tag");
    $statement->execute();

    return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_KEY_PAIR));
  }

  /* The last row `HoldingsCheck`'s sampled pass reached, null before the first
     and after the last. A row rather than a marker file, so a pass survives an
     update and travels with a restored copy. */
  public function holdingsCursor(): ?string
  {
    $id = $this->read(self::HOLDINGS_CURSOR);

    return $id === null ? null : (string) $id;
  }

  public function setHoldingsCursor(?string $id): void
  {
    $this->write(self::HOLDINGS_CURSOR, $this->json->encode($id));
  }

  /* Asked on every request, so they read the one key and leave the findings unread. */
  public function holdingsCheckedAt(): ?\DateTimeImmutable
  {
    $at = $this->read(self::HOLDINGS_REPORT, '$.at');

    return $at === null ? null : new \DateTimeImmutable((string) $at);
  }

  public function holdingsReportVersion(): ?string
  {
    $version = $this->read(self::HOLDINGS_REPORT, '$.version');

    return $version === null ? null : (string) $version;
  }

  public function holdingsReport(): ?HoldingsReport
  {
    $stored = $this->read(self::HOLDINGS_REPORT);
    if ($stored === null) {
      return null;
    }
    $report = $this->json->decode((string) $stored);
    $violations = array_map(fn (array $found): Violation => new Violation((string) $found['field'],
      (string) $found['reason'], $found['record'] === null ? null : new EntityId((string) $found['record'])),
      $report['violations']);

    return new HoldingsReport($violations, new \DateTimeImmutable((string) $report['at']),
      (string) ($report['version'] ?? ''));
  }

  public function setHoldingsReport(HoldingsReport $report): void
  {
    $violations = array_map(fn (Violation $found): array => ['field' => $found->field,
      'reason' => $found->reason, 'record' => $found->record?->value],
      $report->violations);
    $this->write(self::HOLDINGS_REPORT, $this->json->encode(['violations' => $violations,
      'at' => $report->at->format(Clock::STORED), 'version' => $report->version]));
  }

  /* Scalars come back as themselves and a list as its JSON text. */
  private function read(string $key, string $path = '$'): string|int|float|null
  {
    $statement = $this->db->prepare('select json_extract(value, :path) from setting'
      . ' where key = :key');
    $statement->execute(['path' => $path, 'key' => $key]);
    $value = $statement->fetchColumn();

    return $value === false ? null : $value;
  }

  private function write(string $key, string $json): void
  {
    $this->db->prepare('insert into setting (key, value, updated_at)'
      . ' values (:key, :value, :now)'
      . ' on conflict (key) do update set value = excluded.value, updated_at = excluded.updated_at')
      ->execute(['key' => $key, 'value' => $json,
        'now' => $this->clock->now()->format(Clock::STORED)]);
  }
}
