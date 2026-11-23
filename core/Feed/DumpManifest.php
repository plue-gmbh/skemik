<?php

declare(strict_types=1);

namespace Feed;

use Auth\Account;
use Domain\Entity;
use Support\Clock;

/* `manifest.jsonld` at the root of a dump: who took it, under which read scope,
   and every record with the files written for it. Its record keys are this
   app's own: custody has no home in the vocabulary, which is why it travels
   beside the documents. */
final readonly class DumpManifest
{
  public function __construct(
    private DumpFiles $media,
    private Account $taker,
    private string $actor,
    private Clock $clock,
  ) {}

  /** @param array<string, string> $documents by their paths in the box, in the order of `Export::FORMATS`
      @return array<string, mixed> an asset's naming its file */
  public function entry(Entity $entity, array $documents): array
  {
    $filed = $this->media->filed($entity);

    return ['slug' => $entity->slug->value, 'locale' => $entity->locale->tag,
      'status' => $entity->status->value, 'type' => $entity->typeName(), 'version' => $entity->version,
      'files' => array_combine(Export::FORMATS, array_keys($documents)),
      ...($filed === null ? [] : ['file' => $filed[0]])];
  }

  /** @param list<array<string, mixed>> $entries */
  public function of(array $entries): array
  {
    return ['@context' => 'https://schema.org', '@type' => 'Dataset',
      'name' => 'Skemik export', 'dateCreated' => $this->clock->now()->format(Clock::STORED),
      'creator' => ['@type' => 'Person', 'name' => $this->actor],
      'scope' => $this->taker->read->stored() ?? 'every type',
      'authoritative' => Export::AUTHORITATIVE, 'records' => $entries];
  }
}
