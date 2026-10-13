<?php

declare(strict_types=1);

namespace Feed;

use Auth\Authorizer;
use Domain\Entity;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Slug;
use Http\CacheHeaders;
use Http\Preconditions;
use Http\Request;
use Http\Response;
use Http\Route;
use Profile\Profile;
use Profile\SchemaOrg;
use Profile\Seed\Terms;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\RelationRepository;
use Storage\SettingStore;
use Support\Markup;
use Support\Clock;
use Support\Json;
use Support\Words;

/* The read routes, and the one place where who is asking decides what comes
   back. There is one read path and the stranger's reading is a subset of it, not
   a public feed with a private one beside it: were the feed only the public half,
   the bookkeeping, the member list and the till would each need a second read
   path, the backend this product promises to delete. So a collection has an
   address whether or not it can ever be public, and a stranger on one that
   cannot is a `404`, never a `403`, since refusal must not say more than absence.
   It sits in `core/Feed` as `IngestController` sits in `core/Ingest`: a
   controller lives with the machinery it drives. */
final readonly class FeedController
{
  public function __construct(
    private Profile $profile,
    private SchemaOrg $schema,
    private EntityRepository $entities,
    private RelationRepository $relations,
    private CollectionStore $collections,
    private SettingStore $settings,
    private Authorizer $authorizer,
    private FeedRequest $parameters,
    private Targets $targets,
    private Terms $terms,
    private Clock $clock,
    private Words $words,
    private Preconditions $preconditions = new Preconditions(),
    private Json $json = new Json(),
    private ?PublicFilter $fixed = null,
  ) {}

  /* The same routes under a reading handed in: the admin's panel door, `/feed-panel/…`. */
  public function as(PublicFilter $reading): self
  {
    return clone($this, ['fixed' => $reading]);
  }

  public function locales(Request $request): Response
  {
    $document = $this->catalog($request, $this->reading())->locales($this->settings->locales());

    return $this->answer($request, $this->json->encode($document),
      ['Content-Type' => JsonLd::CONTENT_TYPE]);
  }

  public function collections(Request $request, Route $route): Response
  {
    $filter = $this->reading();
    $locale = $this->locale($route->parameter('locale'));
    $document = $this->catalog($request, $filter, $locale)
      ->collections($this->collections->all(), $locale);

    return $this->answer($request, $this->json->encode($document),
      ['Content-Type' => JsonLd::CONTENT_TYPE]);
  }

  /* The same to every caller, since a key widens a reading and never the profile;
     one expired or revoked is refused as on every read, so a site holding it
     stops on its date here too. */
  public function profile(Request $request, Route $route): Response
  {
    if ($request->bearer() !== null) {
      $this->authorizer->requireToken();
    }
    $locale = $this->locale($route->parameter('locale'));
    $profile = new ProfileCatalog($this->profile->worded($this->words->fields($locale->tag)), $this->schema,
      new Variables($this->schema, $this->terms), $request->origin());

    return $this->answer($request, $this->json->encode($profile->document($locale)),
      ['Content-Type' => JsonLd::CONTENT_TYPE]);
  }

  public function list(Request $request, Route $route): Response
  {
    $locale = $this->locale($route->parameter('locale'));
    $filter = $this->reading();
    $held = $this->readable($filter, $route->parameter('collection'));
    /* A collection this caller may read always narrows to something: `readable`
       has already answered the question `query` would return null for. */
    $read = $held->carrying($filter->fields($held));
    $asked = $this->targets->filtered($this->parameters->query($request, $read, $locale), [$locale],
      $this->reads(...));
    $query = $filter->query($asked, $held) ?? throw EntityNotFound::withId($held->slug->value);
    $format = $this->format($request, $route, $filter, $locale);
    $body = $format->listing($this->entities->list($query), $held, $this->entities->count($query),
      $this->address($request, $route));

    return $this->answer($request, $body, ['Content-Type' => $format->contentType()]);
  }

  public function record(Request $request, Route $route): Response
  {
    $locale = $this->locale($route->parameter('locale'));
    $filter = $this->reading();
    $held = $this->readable($filter, $route->parameter('collection'));
    $slug = $route->parameter('slug');
    $entity = $this->entities->bySlug($held->type, $locale, $this->slug($slug));
    if ($entity === null || !$filter->passes($entity, $held)) {
      throw EntityNotFound::withId($slug);
    }

    return $this->document($request, $route, $entity);
  }

  /* The record as this caller reads it, under the tag its next `If-Match` echoes
     — what a record read answers and what a write through the address answers. */
  public function document(Request $request, Route $route, Entity $entity): Response
  {
    $held = $this->collections->bySlug(new Slug($route->parameter('collection')))
      ?? throw EntityNotFound::withId($route->parameter('collection'));
    $locale = $this->locale($route->parameter('locale'));
    $format = $this->format($request, $route, $this->reading(), $locale);
    $body = $format->record($entity, $held);
    $tag = $this->preconditions->recordTag($entity, (new CacheHeaders())->validator($body));

    return $this->answer($request, $body, ['Content-Type' => $format->contentType(), 'ETag' => $tag]);
  }

  /* Whether this caller reads a target, under its collection's reading: asked
     of a filter's target and of one a write names (`Ingest\Edges`), so both
     find only what a document of this reading would show. */
  public function reads(Entity $target): bool
  {
    $held = $this->collections->bySlug(new Slug(Collection::SLUGS[$target->typeName()]));

    return $held !== null && $this->reading()->passes($target, $held);
  }

  /* One question to `Auth\Authorizer` per request: no key and the read runs
     under the anonymous gates, a key and it runs under the grants it holds. */
  private function reading(): PublicFilter
  {
    return $this->fixed ?? new PublicFilter($this->profile, $this->clock, $this->authorizer->machineReads());
  }

  /* The serialisers are built per request because the reading is: one filter,
     one locale, one origin, and four syntaxes over the one document they make. */
  private function format(Request $request, Route $route, PublicFilter $filter,
    Locale $locale): JsonLd|Html|Csv|Markdown
  {
    $addresses = new Addresses($this->profile, $this->entities, $filter, $locale,
      $request->origin(), $this->collections->all());
    $derived = new Derivations($this->profile, $this->schema, $this->entities, $this->relations,
      $addresses, $this->clock);
    $markup = new Markup(words: $this->words->in($locale->tag));
    $document = new JsonLd($this->profile->worded($this->words->fields($locale->tag)), $this->schema,
      $this->entities, $this->relations, $filter, $locale, $request->origin(), $addresses, $derived,
      markup: $this->rendered($request) ? $markup : null);
    $prefix = $request->origin() . '/feed/' . $locale->tag . '/';
    $negotiator = new Negotiator($document,
      new Html($document, Address::linked($prefix, '.html'), $locale, $markup),
      new Csv($document, Address::inTable($prefix)),
      new Markdown($document, Address::linked($prefix, '.md')));

    return $negotiator->choose($route->optional('format'), $request->header('Accept'));
  }

  /* `?render=html` asks the text rendered, at every depth, by the renderer the
     `.html` uses; anything else asked is refused naming it. */
  private function rendered(Request $request): bool
  {
    $asked = $request->query('render');

    return match ($asked) {
      null => false,
      'html' => true,
      default => throw MalformedValue::named('render of html', $asked),
    };
  }

  /* A collection's catalogue entry alone: what arranging it answers with. */
  public function entry(Request $request, Route $route, Collection $collection): Response
  {
    $locale = $this->locale($route->parameter('locale'));
    $document = $this->catalog($request, $this->reading(), $locale)->entry($collection, $locale);

    return $this->answer($request, $this->json->encode($document), ['Content-Type' => JsonLd::CONTENT_TYPE]);
  }

  /* A field is named in the language of the content it is read in, never the
     caller's: a German holding's catalogue says `USt-IdNr.` to every reader. */
  private function catalog(Request $request, PublicFilter $filter, ?Locale $locale = null): Catalog
  {
    $profile = $locale === null ? $this->profile
      : $this->profile->worded($this->words->fields($locale->tag));

    return new Catalog($profile, new Variables($this->schema, $this->terms), $filter, $request->origin(),
      $this->authorizer->machineArranges());
  }

  /* An unknown collection and one this caller has no reading of answer alike:
     absence, because a refusal would confirm that the collection exists. */
  private function readable(PublicFilter $filter, string $slug): Collection
  {
    try {
      $collection = $this->collections->bySlug(new Slug($slug));
    } catch (MalformedValue) {
      $collection = null;
    }

    return $collection !== null && $filter->reads($collection)
      ? $collection
      : throw EntityNotFound::withId($slug);
  }

  /* A locale the house does not hold is no address of this install, whatever
     BCP-47 makes of the tag. */
  private function locale(string $tag): Locale
  {
    foreach ($this->settings->locales() as $locale) {
      if ($locale->tag === $tag) {
        return $locale;
      }
    }

    throw EntityNotFound::withId($tag);
  }

  private function slug(string $value): Slug
  {
    try {
      return new Slug($value);
    } catch (MalformedValue) {
      throw EntityNotFound::withId($value);
    }
  }

  /* What a list document names itself by: the address as it was asked at, since
     two pages of one collection are two documents, but never its `.fmt` spelling,
     which is no canonical one, and never the panel's door, no address at all. */
  private function address(Request $request, Route $route): string
  {
    $format = $route->optional('format');
    $path = $format === null ? $request->path
      : substr($request->path, 0, -strlen('.' . $format));
    $path = (string) preg_replace('#^/feed-panel(?=/|$)#', '/feed', $path);

    return $request->origin() . $path
      . ($request->query === [] ? '' : '?' . http_build_query($request->query));
  }

  /** @param array<string, string> $headers the content type, and a record's tag where there is one */
  private function answer(Request $request, string $body, array $headers): Response
  {
    $cache = new CacheHeaders();
    $headers = [...$cache->feed($request->bearer() !== null || $this->fixed !== null),
      'ETag' => $cache->validator($body), ...$headers];
    if ($cache->matches($request->header('If-None-Match'), $headers['ETag'])) {
      return new Response(304, '', $headers);
    }

    return new Response(200, $body, $headers);
  }
}
