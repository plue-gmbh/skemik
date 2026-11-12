<?php

declare(strict_types=1);

namespace Storage;

use Domain\Locale;
use Support\Clock;
use Support\Json;

/* The organisation's own sites, for the header: the public `WebSite`s a `WebPage` is
   `isPartOf`, or, where none stands, the public `WebPage`s that state a `url` and name
   no site, since an install built from its home page alone holds no `WebSite`. Beside a
   site such a page is somebody else's, so it is never linked there. Read from the
   holdings and never detected, since a feed read carries nothing that names its site;
   not every published `WebSite`, since a showcase list holds other people's sites. A
   site built wholly in code holds no page here and gets no link, a missing one and
   never a wrong one. A host is linked once, at the shortest address stated under it,
   so a home page stands for its `/en`. The install's first language goes first, so the
   first link is the site it writes in. */
final readonly class Sites
{
  public const WEB_SITE = 'https://schema.org/WebSite';
  public const WEB_PAGE = 'https://schema.org/WebPage';
  private const SITES = 'e.type = :site and exists (select 1 from relation r join entity p on p.id = r.subject_id'
    . " where r.object_id = e.id and r.predicate = 'isPartOf' and p.type = :page and p.deleted_at is null)";
  private const PAGES = 'e.type = :page and not exists (select 1 from relation r'
    . " where r.subject_id = e.id and r.predicate = 'isPartOf')";

  public function __construct(
    private \PDO $db,
    private Clock $clock,
    private SettingStore $settings,
    private Json $json = new Json(),
  ) {}

  /** @param list<string> $types of `WEB_SITE` and `WEB_PAGE`, those whose `url` may be shown
      @return array<string, string> each host by the shortest URL stated under it, in the install's languages' order */
  public function linked(array $types): array
  {
    $sites = $this->stated(self::SITES, ['site' => self::WEB_SITE, 'page' => self::WEB_PAGE]);
    [$shown, $stated] = $sites === [] ? [self::WEB_PAGE, $this->stated(self::PAGES, ['page' => self::WEB_PAGE])]
      : [self::WEB_SITE, $sites];
    $byHost = [];
    foreach (in_array($shown, $types, true) ? array_filter($stated, is_string(...)) : [] as $url) {
      $host = parse_url($url, PHP_URL_HOST);
      if (is_string($host) && $host !== '' && (!isset($byHost[$host]) || strlen($url) < strlen($byHost[$host]))) {
        $byHost[$host] = $url;
      }
    }

    return array_flip($byHost);
  }

  /** The `url` of each public record the clause names, null where it states none: a site that states none
      still stands.
      @param array<string, string> $named the types the clause binds
      @return list<?string> */
  private function stated(string $clause, array $named): array
  {
    $statement = $this->db->prepare("select json_extract(e.props, '$.url') from entity e where " . $clause
      . ' and e.deleted_at is null and ' . Query::PUBLIC
      . ' order by coalesce((select l.key from json_each(:locales) l where l.value = e.locale),'
      . ' json_array_length(:locales)), e.slug');
    $statement->execute([...$named, 'now' => $this->clock->now()->format(Clock::STORED),
      'locales' => $this->languages()]);

    return $statement->fetchAll(\PDO::FETCH_COLUMN);
  }

  /* A site in a language the install no longer holds goes last. */
  private function languages(): string
  {
    return $this->json->encode(array_map(fn (Locale $locale): string => $locale->tag, $this->settings->locales()));
  }
}
