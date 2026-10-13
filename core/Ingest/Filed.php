<?php

declare(strict_types=1);

namespace Ingest;

use Admin\Intake;
use Admin\UploadRefused;
use Domain\Entity;
use Domain\Exceptions\MalformedValue;
use Http\Response;
use Http\Upload;
use Profile\SchemaOrg;
use Storage\Collection;
use Support\Json;

/* An asset posted with its file, the document beside it in one multipart request,
   so a dump's library moves by the address as its other records do. The file
   says what it is — its address, format, size, hash and pixels — and the
   document says everything else, its upload date included, so the copy differs
   from what was read only in `contentUrl`, which names the new file. The file is
   taken by the library's own door (`Admin\Intake`), and dropped again when the
   record is not written, so no file is left that no record holds. */
final readonly class Filed
{
  private const OWN = ['content_url', 'encoding_format', 'content_size', 'sha256', 'width', 'height'];

  public function __construct(private Intake $intake, private SchemaOrg $schema, private Json $json = new Json()) {}

  /* The document without what the file says of itself.
     @param array<string, mixed> $document
     @return array<string, mixed> */
  public function document(Collection $collection, array $document): array
  {
    if (basename($collection->type) !== 'MediaObject') {
      throw MalformedValue::named('collection taking a file', $collection->name);
    }
    $spelt = array_map(fn (string $key): string => $this->schema->propertyName($key) ?? $key, self::OWN);

    return array_diff_key($document, array_flip($spelt));
  }

  /** @param \Closure(Entity): (Entity|Response) $write */
  public function written(Upload $upload, Entity $draft, \Closure $write): Entity|Response
  {
    try {
      $file = $this->intake->file($upload);
    } catch (UploadRefused $refused) {
      return Response::json($this->json->encode(['error' => $refused->getMessage()]), 422);
    }
    try {
      $saved = $write(new Entity($draft->id, $draft->type, $draft->locale, $draft->slug, $file,
        $draft->createdAt, $draft->updatedAt));
    } catch (\Throwable $failure) {
      $this->intake->drop($file);
      throw $failure;
    }
    if ($saved instanceof Response) {
      $this->intake->drop($file);
    }

    return $saved;
  }
}
