<?php

declare(strict_types=1);

namespace Storage;

use Domain\Locale;
use Support\Clock;
use Support\Json;

/* The house's own sites, for the header: the public `WebSite`s a `WebPage` is
   `isPartOf`, one link per host. Read from the holdings and never detected, since
   a feed read carries nothing that names its site; not every published `WebSite`,
   since a showcase list holds other people's sites. A site built wholly in code
   holds no page here and gets no link, a missing one and never a wrong one. The
   house's first language goes first, so the first link is the site it writes in. */
final readonly class Sites
{
  public const WEB_SITE = 'https://schema.org/WebSite';
  private const WEB_PAGE = 'https://schema.org/WebPage';

  public function __construct(
    private \PDO $db,
    private Clock $clock,
    private SettingStore $settings,
    private Json $json = new Json(),
  ) {}

  /** @return array<string, string> each host by the first URL stated under it, in the house's languages' order */
  public function linked(): array
  {
    $statement = $this->db->prepare("select json_extract(e.props, '$.url') from entity e"
      . ' where e.type = :site and e.deleted_at is null and ' . Query::PUBLIC
      . ' and exists (select 1 from relation r join entity p on p.id = r.subject_id'
      . " where r.object_id = e.id and r.predicate = 'isPartOf' and p.type = :page and p.deleted_at is null)"
      . ' order by coalesce((select l.key from json_each(:locales) l where l.value = e.locale),'
      . ' json_array_length(:locales)), e.slug');
    $statement->execute(['site' => self::WEB_SITE, 'page' => self::WEB_PAGE,
      'now' => $this->clock->now()->format(Clock::STORED), 'locales' => $this->languages()]);
    $byUrl = [];
    foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $url) {
      $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
      if (is_string($host) && $host !== '' && !in_array($host, $byUrl, true)) {
        $byUrl[$url] = $host;
      }
    }

    return $byUrl;
  }

  /* A site in a language the house no longer holds goes last. */
  private function languages(): string
  {
    return $this->json->encode(array_map(fn (Locale $locale): string => $locale->tag, $this->settings->locales()));
  }
}
