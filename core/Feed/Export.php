<?php

declare(strict_types=1);

namespace Feed;

use Auth\Account;
use Auth\Authorizer;
use Domain\Entity;
use Domain\Locale;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Query;
use Storage\RelationRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\Json;
use Support\Words;
use Support\Zip;

/* The dump an organisation takes with it, written into one box: a tree per format, a
   file per record and a listing per page, the files under `media/`, and
   `manifest.jsonld` at the root. It runs under the reader's read scope through
   `PublicFilter`, so every question of what comes out is that one object's
   answer and the box can never hold a record its reader could not open on
   screen, nor lack one they could; the role is not consulted, since a `viewer`
   reading everything dumps everything. The catalogue shows what the taker may
   arrange as a key that arranges it reads it, since which fields leave the
   install is the one thing a dump posted back could not restore otherwise. */
final readonly class Export
{
  public const FORMATS = ['jsonld', 'html', 'csv', 'md'];
  public const FILES = 'media/';
  public const AUTHORITATIVE = 'jsonld';
  /* The records a collection is weighed on before the run, per language: few
     enough that the screen opens at once, and the first page as the feed lists it. */
  private const SAMPLE = 10;

  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private CollectionStore $collections,
    private SettingStore $settings,
    private PublicFilter $filter,
    private Authorizer $authorizer,
    private Account $taker,
    private string $origin,
    private DumpFiles $media,
    private DumpManifest $manifest,
    private Zip $box,
    private Clock $clock,
    private Terms $terms,
    private Words $words,
    private Json $json = new Json(),
  ) {}

  /* Said before the run, since the box is as large as its files and documents.
     The documents are weighed on a sample written in memory, never into the box,
     so the figure follows what the serialisers write and no guess per record.
     The serialisers are a language's here and no collection's: each set words
     the whole profile anew, which was most of the screen's wait. */
  public function measure(): DumpSize
  {
    $records = 0;
    $bytes = 0;
    foreach ($this->settings->locales() as $locale) {
      $bytes += $this->weight($this->index($locale));
      $formats = $this->serialisers($locale);
      foreach ($this->readable() as $held) {
        $query = $this->filter->query(new Query($held->type, locale: $locale), $held);
        $count = $query === null ? 0 : $this->entities->count($query);
        $records += $count;
        $bytes += $this->weighed($held, $locale, $formats, $count);
      }
    }
    $files = $this->files();
    foreach ($files as $path => $file) {
      $bytes += Zip::weight($path, (int) filesize($file));
    }

    return new DumpSize($records, count($files), $bytes);
  }

  /** A collection in one language, scaled from its sample: the records, their
      manifest entries and their listing, since the pages of 200 hold what the
      sample's listing holds for ten. An empty one still writes its listing and
      is not read: for a language no record of a type is in, a read goes through the whole type.
      @param array<string, JsonLd|Html|Csv|Markdown> $formats */
  private function weighed(Collection $held, Locale $locale, array $formats, int $count): int
  {
    $query = $this->filter->query(new Query($held->type, locale: $locale, perPage: self::SAMPLE), $held);
    if ($query === null) {
      return 0;
    }
    $members = $count === 0 ? [] : $this->entities->list($query);
    $bytes = $this->weight($this->listed($members, $held, $formats, $query, $count));
    foreach ($members as $member) {
      $documents = $this->documents($member, $held, $formats, $locale->tag . '/' . $held->slug->value);
      $bytes += $this->weight($documents) + strlen($this->json->encode($this->manifest->entry($member, $documents)));
    }

    return $members === [] ? $bytes : intdiv($bytes * $count, count($members));
  }

  /** @param array<string, string> $documents */
  private function weight(array $documents): int
  {
    return array_sum(array_map(fn (string $path, string $body): int => Zip::weight($path, strlen($body)),
      array_keys($documents), $documents));
  }

  /** @return int the records written, over every locale the install holds */
  public function write(): int
  {
    $entries = [];
    foreach ($this->settings->locales() as $locale) {
      $this->put($this->index($locale));
      foreach ($this->readable() as $held) {
        $entries = [...$entries, ...$this->collection($held, $locale)];
      }
    }
    foreach ($this->files() as $path => $file) {
      $this->box->addFile($path, $file);
    }
    $this->put(['manifest.jsonld' => $this->json->encode($this->manifest->of($entries))]);
    $this->box->close();

    return count($entries);
  }

  /** @return list<Collection> */
  private function readable(): array
  {
    return array_values(array_filter($this->collections->all(), $this->filter->reads(...)));
  }

  /** A page at a time, and the listing written per page as the address serves
      it: a collection of 27 000 articles is one walk and never one document, and
      the serialisers are the page's, so what they remember leaves with it. The
      collection is counted once: counted per page, it was read whole for each.
      @return list<array<string, mixed>> the manifest entries of this collection */
  private function collection(Collection $held, Locale $locale): array
  {
    $folder = $locale->tag . '/' . $held->slug->value;
    $entries = [];
    $total = null;
    for ($page = 1; ; $page++) {
      $query = $this->filter->query(new Query($held->type, locale: $locale, page: $page,
        perPage: Query::MAX_PER_PAGE), $held);
      if ($query === null) {
        return $entries;
      }
      $total ??= $this->entities->count($query);
      $formats = $this->serialisers($locale);
      $members = $this->entities->list($query);
      foreach ($members as $member) {
        $entries[] = $this->record($member, $held, $formats, $folder);
      }
      $this->put($this->listed($members, $held, $formats, $query, $total));
      /* No empty page is written past the last. */
      if ($page * Query::MAX_PER_PAGE >= $total) {
        return $entries;
      }
    }
  }

  /** @param array<string, JsonLd|Html|Csv|Markdown> $formats
      @return array<string, mixed> the record's manifest entry */
  private function record(Entity $entity, Collection $held, array $formats, string $folder): array
  {
    $documents = $this->documents($entity, $held, $formats, $folder);
    $this->put($documents);

    return $this->manifest->entry($entity, $documents);
  }

  /** @param array<string, JsonLd|Html|Csv|Markdown> $formats
      @return array<string, string> each format's document by its path in the box */
  private function documents(Entity $entity, Collection $held, array $formats, string $folder): array
  {
    $documents = [];
    foreach ($formats as $format => $serialiser) {
      $documents[$format . '/' . $folder . '/' . $entity->slug->value . '.' . $format] =
        $serialiser->record($entity, $held);
    }

    return $documents;
  }

  /** Each page carries the address it is served at, pages of 200 said in it, and
      the collection's whole total, so a link into the box is a feed link with the
      prefix swapped and a reader holding one file knows how many records there were.
      @param list<Entity> $members
      @param array<string, JsonLd|Html|Csv|Markdown> $formats
      @return array<string, string> the page by its path in the box */
  private function listed(array $members, Collection $held, array $formats, Query $query, int $total): array
  {
    $locale = $query->locale ?? $this->settings->locales()[0];
    $first = $query->page === 1;
    $asked = ['per_page' => $query->perPage, ...($first ? [] : ['page' => $query->page])];
    $address = $this->prefix($locale) . $held->slug->value . '?' . http_build_query($asked);
    $file = $locale->tag . '/' . $held->slug->value . '/' . ($first ? 'index' : 'page-' . $query->page);
    $documents = [];
    foreach ($formats as $format => $serialiser) {
      $documents[$format . '/' . $file . '.' . $format] = $serialiser->listing($members, $held, $total, $address);
    }

    return $documents;
  }

  /** @return array<string, string> the path in the box to the file on disk */
  private function files(): array
  {
    $library = $this->profile->byType('MediaObject')->url();
    $held = array_find($this->readable(), fn (Collection $each): bool => $each->type === $library);

    return $held === null ? [] : $this->media->of($held, $this->settings->locales());
  }

  /** @return array<string, JsonLd|Html|Csv|Markdown> */
  private function serialisers(Locale $locale): array
  {
    $held = $this->collections->all();
    $addresses = new Addresses($this->profile, $this->entities, $this->filter, $locale, $this->origin, $held);
    $derived = new Derivations($this->profile, $this->schema, $this->entities, $this->relations,
      fn (Entity $row): bool => $addresses->collectionOf($row) !== null, $this->filter->readings($held),
      $this->clock);
    $document = new JsonLd($this->worded($locale), $this->schema, $this->entities, $this->relations,
      $this->filter, $locale, $this->origin, $addresses, $derived, built: new \ArrayObject());
    $address = fn (string $format): Address =>
      Address::inDump($this->prefix($locale), '.' . $format, $this->origin . '/media/');

    $formats = ['jsonld' => $document, 'html' => new Html($document, $address('html'), $locale),
      'csv' => new Csv($document, $address('csv')),
      'md' => new Markdown($document, $address('md'))];
    /* The page's claim is the box holding all four: a format dropped here is a
       promise broken in a folder nobody re-reads. */
    assert(array_keys($formats) === self::FORMATS, 'the dump writes the four the routes serve');

    return $formats;
  }

  /* The fields' names and the enumerations' words in the language of the
     content, which an organisation that leaves takes with it: nothing else holds them. */
  private function catalog(Locale $locale): Catalog
  {
    $arranged = array_filter($this->collections->all(),
      fn (Collection $held): bool => $this->authorizer->arranges($this->taker, $held));

    return new Catalog($this->worded($locale), new Variables($this->schema, $this->terms), $this->filter,
      $this->origin, array_values(array_map(fn (Collection $held): string => $held->slug->value, $arranged)));
  }

  /** @return array<string, string> the fields' names in one language, by its path in the box */
  private function index(Locale $locale): array
  {
    return [self::AUTHORITATIVE . '/' . $locale->tag . '/index.jsonld' => $this->json->encode(
      $this->catalog($locale)->collections($this->collections->all(), $locale))];
  }

  private function worded(Locale $locale): Profile
  {
    return $this->profile->worded($this->words->fields($locale->tag));
  }

  private function prefix(Locale $locale): string
  {
    return $this->origin . '/feed/' . $locale->tag . '/';
  }

  /** @param array<string, string> $documents by their paths in the box */
  private function put(array $documents): void
  {
    foreach ($documents as $path => $body) {
      /* `Address` writes one `../` into every link in the box, which is right only
         while every document sits at `<format>/<locale>/<collection>/`; a catalog
         and the manifest link to no file. */
      assert(substr_count($path, '/') === 3 || str_ends_with($path, '/index.jsonld')
        || $path === 'manifest.jsonld', 'a document of the dump sits two folders under its format tree');
      $this->box->add($path, $body);
    }
  }
}
