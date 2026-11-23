<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Domain\Locale;
use Profile\Seed\Media;
use Storage\Collection;
use Storage\EntityRepository;
use Storage\Query;

/* The library's files a dump carries under `media/`, read through the same
   `PublicFilter` as the documents beside them, so the box holds a file only where
   its reader may open the asset. */
final readonly class DumpFiles
{
  public function __construct(
    private EntityRepository $entities,
    private PublicFilter $filter,
    private string $media,
  ) {}

  /** Each file once, however many locales hold the asset.
      @param list<Locale> $locales
      @return array<string, string> the path in the box to the file on disk */
  public function of(Collection $library, array $locales): array
  {
    $files = [];
    foreach ($locales as $locale) {
      foreach ($this->assets($library, $locale) as $asset) {
        $filed = $this->filed($asset);
        $filed === null || $files[$filed[0]] = $filed[1];
      }
    }

    return $files;
  }

  /** A row whose file is gone is the asset nobody has, as the address answers it.
      @return ?array{string, string} the path in the box and the file on disk */
  public function filed(Entity $entity): ?array
  {
    $extension = Media::FORMATS[(string) $entity->props->string('encoding_format')] ?? null;
    if ($entity->typeName() !== 'MediaObject' || $extension === null) {
      return null;
    }
    /** @psalm-taint-escape file
        @psalm-taint-escape ssrf
        @psalm-taint-escape url_component
        @psalm-taint-escape url_path */
    $name = basename((string) $entity->props->string('content_url')) . '.' . $extension; /* kept in the folder */
    $file = $this->media . '/' . $name;

    return is_file($file) ? [Export::FILES . $name, $file] : null;
  }

  /** @return \Generator<int, Entity> */
  private function assets(Collection $library, Locale $locale): \Generator
  {
    for ($page = 1; ; $page++) {
      $query = $this->filter->query(new Query($library->type, locale: $locale, page: $page,
        perPage: Query::MAX_PER_PAGE), $library);
      $members = $query === null ? [] : $this->entities->list($query);
      yield from $members;
      if (count($members) < Query::MAX_PER_PAGE) {
        return;
      }
    }
  }
}
