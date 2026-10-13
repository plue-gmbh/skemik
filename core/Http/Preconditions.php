<?php

declare(strict_types=1);

namespace Http;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\StaleWrite;

/* Conditional writes, in one file because they are a rule and not an idea two
   controllers may each interpret. A token's write without `If-Match` fails here,
   and a person's form carries the version it loaded, so the admin is protected
   without anyone thinking about it. The feed's tag carries the record's id beside
   its version, since a slug is reusable and a tag echoed to a slug another record
   has taken must answer `412`; the document's hash rides along for
   `If-None-Match`. `If-Match: *` is refused as the blind overwrite this exists to
   stop, and a tag a compressing server rewrote is read through, the version and
   the id being compared and never the bytes. */
final readonly class Preconditions
{
  private const FIELD = 'version';
  private const TAG = '/^(?:W\/)?"?([1-9]\d{0,9})(?:-([0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})-[0-9a-f]+)?'
    . '(?:-(?:gzip|br|zstd|deflate))?"?$/';

  public function etag(int $version): string
  {
    return sprintf('"%d"', $version);
  }

  /* @param string $validator `CacheHeaders::validator` of the document served */
  public function recordTag(Entity $entity, string $validator): string
  {
    return sprintf('"%d-%s-%s"', $entity->version, $entity->id->value, trim($validator, '"'));
  }

  /* No write without a version, on either path: a write that forgot to say what
     it read is the lost update this mechanism exists to refuse. A write aimed at
     a record by its slug must echo the tag that record was read under. */
  public function expectedVersion(Request $request, ?EntityId $record = null): int
  {
    $sent = $request->header('If-Match') ?? $request->form(self::FIELD);
    if ($sent === null || preg_match(self::TAG, trim($sent), $tag) !== 1
      || ($record !== null && ($tag[2] ?? '') === '')) {
      throw new PreconditionRequired('a write names the version it read, as If-Match');
    }
    if ($record !== null && $tag[2] !== $record->value) {
      throw StaleWrite::otherRecord();
    }

    return (int) $tag[1];
  }
}
