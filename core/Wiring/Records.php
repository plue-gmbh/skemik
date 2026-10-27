<?php

declare(strict_types=1);

namespace Wiring;

use Admin\CollectionController;
use Admin\EdgeEdits;
use Admin\FieldController;
use Admin\FieldEdits;
use Admin\MediaController;
use Admin\PreviewController;
use Admin\Prose;
use Admin\QueryBar;
use Admin\RecordController;
use Admin\RecordPage;
use Admin\RecordWords;
use Admin\SearchController;
use Admin\SpineDialog;
use Admin\SpineEdits;
use Admin\TermMaker;
use Admin\TranslationController;
use Admin\TrashController;
use Http\Endpoint;
use Http\Response;
use Http\Route;
use Storage\Trash;

/* The collection screen and everything it embeds, one area because a record,
   the trash, a field and a medium each answer through the collection's controller
   or inside its page. */
final readonly class Records
{
  public function __construct(private Common $common) {}

  public function records(Route $route): Response
  {
    [$common, $segment] = [$this->common, $route->parameter(...)];
    $account = $common->authorizer->requireAccount(...);
    return match ($route->endpoint) {
      Endpoint::Collection => $this->collectionController()->show($account(), $common->requirePerson(),
        $common->request, $segment('collection')),
      Endpoint::Search => (new SearchController($common->search, $common->entities, $common->collections,
        $common->rows, $common->profile, $common->schema, $common->targets, $common->relations,
        $common->authorizer))->hits($account(), $common->request),
      Endpoint::Preview => (new PreviewController($this->prose(), $common->csrf))->render($account(),
        $common->request),
      Endpoint::Record => $this->recordController()->show($account(), $common->requirePerson(),
        $common->request, $segment('collection'), $segment('slug')),
      Endpoint::RecordCreate => $this->recordController()->create($account(), $common->request,
        $segment('collection')),
      Endpoint::RecordPatch => $this->recordController()->patch($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordTranslation => $this->translationController()->translate($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordDuplicate => $this->translationController()->duplicate($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordLink => $this->translationController()->link($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordUnlink => $this->translationController()->unlink($account(), $common->request,
        $segment('collection'), $segment('id')),
    };
  }

  public function trash(Route $route): Response
  {
    [$common, $segment] = [$this->common, $route->parameter(...)];
    $account = $common->authorizer->requireAccount(...);
    return match ($route->endpoint) {
      Endpoint::Trash => $this->trashController()->show($account(), $common->requirePerson(),
        $common->request, $segment('collection')),
      Endpoint::RecordDelete => $this->trashController()->delete($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordRestore => $this->trashController()->restore($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordPurge => $this->trashController()->purge($account(), $common->request,
        $segment('collection'), $segment('id')),
      Endpoint::RecordsDelete => $this->trashController()->deleteMany($account(), $common->request,
        $segment('collection')),
      Endpoint::RecordsRestore => $this->trashController()->restoreMany($account(), $common->request,
        $segment('collection')),
      Endpoint::RecordsPurge => $this->trashController()->purgeMany($account(), $common->request,
        $segment('collection')),
    };
  }

  public function fields(Route $route): Response
  {
    [$request, $segment] = [$this->common->request, $route->parameter(...)];
    return match ($route->endpoint) {
      Endpoint::FieldAdd => $this->fieldController()->add($request, $segment('collection')),
      Endpoint::FieldEdit => $this->fieldController()->edit($request, $segment('collection'), $segment('key')),
      Endpoint::FieldRemove => $this->fieldController()->remove($request, $segment('collection'),
        $segment('key')),
    };
  }

  public function media(Route $route): Response
  {
    [$common, $segment] = [$this->common, $route->parameter(...)];
    return match ($route->endpoint) {
      Endpoint::MediaUpload => $this->mediaController()->upload($common->request),
      Endpoint::MediaReplace => $this->mediaController()->replace($common->request, $segment('collection'),
        $segment('id')),
      Endpoint::MediaFile => $this->mediaController()->file($common->account, $common->request, $segment('name')),
      Endpoint::MediaThumb => $this->mediaController()->thumb($common->account, $common->request,
        $segment('name')),
    };
  }

  private function collectionController(): CollectionController
  {
    $common = $this->common;
    return new CollectionController($common->collections, $common->entities, $common->authorizer,
      $common->filter, $common->named, $common->schema, $common->sheet(), $this->dialog(), $this->bar(),
      $this->mediaController(), $this->fieldController(), $common->settings, $common->frame(),
      $common->session, $common->reviews(), $common->keys(), $common->clock, $common->csrf,
      $common->template, $common->words, $common->rows);
  }

  private function recordController(): RecordController
  {
    $common = $this->common;
    return new RecordController($this->collectionController(), $common->frame(), $this->recordPage(),
      $common->entities,
      $common->relations, $common->validator, $common->showing(), $common->authorizer, $common->log,
      $common->sheet(),
      new FieldEdits(new EdgeEdits($common->entities, $common->rows, $common->predicates, $common->schema,
        $this->terming(), $common->targets), $common->schema, $common->profile, $common->settings, $common->clock,
        $common->request->origin()),
      $this->spineEdits(), $this->dialog(), $this->recordWords(), $common->profile, $common->settings,
      $common->uuid, $common->clock, $common->csrf, $common->template, $common->words, $common->db);
  }

  private function translationController(): TranslationController
  {
    $common = $this->common;
    return new TranslationController($this->collectionController(), $common->entities, $common->relations,
      $common->authorizer, $this->spineEdits(), $common->profile, $common->predicates, $common->uuid,
      $common->clock, $common->csrf, $common->db);
  }

  private function spineEdits(): SpineEdits
  {
    $common = $this->common;
    return new SpineEdits($common->collisions, $common->entities, $common->profile, $common->settings,
      $common->clock, $common->words);
  }

  private function recordPage(): RecordPage
  {
    $common = $this->common;
    return new RecordPage($common->entities, $common->showing(), $common->collections, $common->settings,
      $this->bar(), $common->sheet(), $common->rows, $this->prose(), $this->dialog(),
      $this->recordWords(), $common->accounts, $common->named, $common->schema, $common->csrf,
      $common->template, $common->words, $common->reviews(), $common->keys());
  }

  private function prose(): Prose
  {
    $common = $this->common;
    return new Prose($common->entities, $common->collections, $common->rows, $common->profile);
  }

  private function trashController(): TrashController
  {
    $common = $this->common;
    return new TrashController($this->collectionController(), $common->frame(), $common->entities,
      new Trash($common->db, $common->entities, $common->relations,
        $common->accounts, $common->log, $common->profile, $common->schema, $common->clock),
      $common->relations, $common->log, $common->authorizer, $common->session, $common->profile,
      $common->intake(), $common->moments, $common->csrf, $common->template, $common->words);
  }

  private function fieldController(): FieldController
  {
    $common = $this->common;
    return new FieldController($common->collections, $common->authorizer, $common->named,
      $common->schema, $common->session, $common->csrf, $common->words, $common->log);
  }

  /* A library's first view is fifty cold previews, and a stranger's a hundred more. */
  private function mediaController(): MediaController
  {
    $common = $this->common;
    return new MediaController($common->collections, $common->entities, $common->relations,
      $common->showing(), $common->rows, $common->intake(), $common->authorizer, $common->clock,
      $common->session, $common->thumbnails, $common->limit(150, 60), $common->profile, $common->csrf,
      $common->words, $common->premises->data . '/holdings/media');
  }

  private function dialog(): SpineDialog
  {
    $common = $this->common;
    return new SpineDialog($common->entities, $common->settings, $common->rows, $common->sheet(),
      $common->named, $common->log, $common->clock, $common->moments, $common->words);
  }

  private function bar(): QueryBar
  {
    $common = $this->common;
    return new QueryBar($common->parameters, $common->filter, $common->authorizer, $common->entities,
      $common->collections, $common->counts, $common->settings, $common->rows, $common->named, $common->terms,
      $common->targets, $common->words);
  }

  private function recordWords(): RecordWords
  {
    $common = $this->common;
    return new RecordWords($common->sheet(), $common->entities, $common->settings, $common->profile,
      $common->words);
  }

  private function terming(): TermMaker
  {
    $common = $this->common;
    return new TermMaker($common->entities, $common->targets, $common->predicates,
      $common->settings->locales()[0], $common->uuid, $common->clock);
  }

}
