<?php

declare(strict_types=1);

namespace Feed;

use Profile\Seed\Media;
use Storage\Query;

/* How `Html`, `Csv` and `Markdown` write the address of a record they link to.
   Served, a page links the whole address in its own format, so a reader who came
   for `.html` follows a link to `.html`; a table drops the locale's prefix, for
   the reason `Csv` gives. In a dump there is no server to resolve anything, so a
   link is the file beside it, `../organizations/acme.html`, or the box would be
   a folder whose links all 404, a demonstration that you cannot leave. One `../`
   reaches every collection because every document sits two folders down, so this
   holds a step and a suffix rather than a path per pair of records. */
final readonly class Address
{
  private function __construct(
    private string $prefix,
    private string $base,
    private string $suffix,
    private string $uploads = '',
  ) {}

  public static function linked(string $prefix, string $suffix): self
  {
    return new self($prefix, $prefix, $suffix);
  }

  public static function inTable(string $prefix): self
  {
    return new self($prefix, '', '');
  }

  /** @param string $uploads the address the house serves its files under */
  public static function inDump(string $prefix, string $suffix, string $uploads): self
  {
    return new self($prefix, '../', $suffix, $uploads);
  }

  /* Served, a file is its address; in the box it is the file beside the
     documents, where its format names it, and the address where nothing does. */
  public function file(string $url, string $format): string
  {
    $extension = Media::FORMATS[$format] ?? null;
    if ($this->uploads === '' || $extension === null || !str_starts_with($url, $this->uploads)) {
      return $url;
    }

    return '../../' . $this->base . Export::FILES . substr($url, strlen($this->uploads)) . '.' . $extension;
  }

  /* The page after the one a listing was asked at, or null on its last. */
  public static function next(string $listing, int $total): ?string
  {
    $query = [];
    parse_str((string) parse_url($listing, PHP_URL_QUERY), $query);
    $page = (int) ($query['page'] ?? 1);
    $asked = (int) ($query['per_page'] ?? Query::PER_PAGE);

    return $page * $asked >= $total ? null
      : strtok($listing, '?') . '?' . http_build_query([...$query, 'page' => $page + 1]);
  }

  /* An address elsewhere, as a text links one, is left whole: it is nobody's file here. */
  public function of(string $id): string
  {
    if (!str_starts_with($id, $this->prefix)) {
      return $id;
    }
    [$path, $query] = array_pad(explode('?', substr($id, strlen($this->prefix)), 2), 2, '');
    if ($this->uploads !== '' && $query !== '') {
      return $this->base . $path . '/' . $this->page($query) . $this->suffix;
    }

    return $this->base . $path . $this->suffix . ($query === '' ? '' : '?' . $query);
  }

  private function page(string $query): string
  {
    $asked = [];
    parse_str($query, $asked);
    $page = (int) ($asked['page'] ?? 1);

    return $page === 1 ? 'index' : 'page-' . $page;
  }
}
