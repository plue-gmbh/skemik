<?php

declare(strict_types=1);

namespace Wiring;

use Domain\Entity;
use Feed\FeedController;
use Feed\PublicFilter;
use Http\Endpoint;
use Http\Response;
use Http\Route;
use Http\SecurityText;
use Ingest\ArrangeController;
use Ingest\Arrangement;
use Ingest\Edges;
use Ingest\Filed;
use Ingest\IngestController;
use Ingest\ObjectRows;
use Ingest\PayloadMapper;
use Update\Version;

/* The feed address, read and written, which keeps no session, and the panel's
   reading of it, which answers the colleague logged in what the address would
   answer a key reading as they read. */
final readonly class Address
{
  public function __construct(private Common $common) {}

  public function answer(Route $route): Response
  {
    $request = $this->common->request;
    return match ($route->endpoint) {
      Endpoint::FeedCatalog => $this->feed()->locales($request),
      Endpoint::FeedLocale => $this->feed()->collections($request, $route),
      Endpoint::FeedProfile => $this->feed()->profile($request, $route),
      Endpoint::FeedCollection => $this->feed()->list($request, $route),
      Endpoint::FeedRecord => $this->feed()->record($request, $route),
      Endpoint::IngestCreate => $this->ingest()->create($request, $route),
      Endpoint::IngestPatch => $this->ingest()->patch($request, $route),
      Endpoint::IngestArrange => $this->arranger()->arrange($request, $route),
      Endpoint::PanelLocale => $this->staff()->collections($request, $route),
      Endpoint::PanelCollection => $this->staff()->list($request, $route),
      Endpoint::PanelRecord => $this->staff()->record($request, $route),
      Endpoint::FeedPreflight => new Response(204),
      Endpoint::Robots => new Response(200, "User-agent: *\nDisallow: /\n", ['Content-Type' => 'text/plain']),
      Endpoint::SecurityText => (new SecurityText($this->common->root,
        $this->common->premises->securityContact, Version::RELEASED))->answer(),
    };
  }

  private function feed(): FeedController
  {
    $common = $this->common;
    return new FeedController($common->profile, $common->schema, $common->entities, $common->relations,
      $common->collections, $common->settings, $common->authorizer, $common->parameters, $common->targets,
      $common->terms, $common->clock, $common->words);
  }

  private function staff(): FeedController
  {
    $common = $this->common;
    return $this->feed()->as(new PublicFilter($common->profile, $common->clock, null,
      $common->authorizer->requireAccount()->read));
  }

  /* A re-import of a dump is a till with a long queue: ten writes a second per key and address. */
  private function ingest(): IngestController
  {
    [$common, $targets, $origin] = [$this->common, $this->common->targets, $this->common->request->origin()];
    $reads = fn (Entity $target): bool => $this->feed()->reads($target);
    return new IngestController($common->authorizer, $this->feed(), $common->collections, $common->settings,
      $common->entities, $common->relations, $common->collisions,
      new PayloadMapper($common->profile, $common->schema, $targets, $common->collections,
        new ObjectRows($common->profile, $common->schema, $targets, $reads),
        new Edges($common->schema, $common->predicates, $targets, $reads), $origin, $reads),
      $common->validator, $common->profile, $common->schema, $common->limit(600, 60), $common->limit(600, 60),
      $common->uuid, $common->clock, $common->entitiesFor(...),
      new Filed($common->intake(), $common->schema));
  }

  /* An arrangement counts in the same minute as a record write. */
  private function arranger(): ArrangeController
  {
    $common = $this->common;
    return new ArrangeController($common->authorizer, $this->feed(), $common->collections, $common->settings,
      $common->profile, new Arrangement($common->schema), $common->limit(600, 60), $common->limit(600, 60),
      $common->logFor(...));
  }
}
