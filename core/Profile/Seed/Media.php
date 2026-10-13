<?php

declare(strict_types=1);

namespace Profile\Seed;

use Domain\Entity;
use Profile\Erasure;
use Profile\FieldDefinition;
use Profile\LocaleMode;
use Profile\Reach;
use Profile\TypeDefinition;

/* One library for every asset, its emitted `@type` derived from `encodingFormat`
   and never authored: an image is an `ImageObject`, a video a `VideoObject`, a
   sound an `AudioObject`, anything else a `MediaObject`. */
final readonly class Media
{
  /* What an upload may be, by its sniffed type, and the extension its
     stored name gets; `mpeg` and `ogg` are sniffed as audio or video. Part of the
     profile and not a setting, so another entry is the conversation another
     `FieldKind` would be. */
  public const FORMATS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    'image/avif' => 'avif', 'image/gif' => 'gif', 'image/svg+xml' => 'svg', 'application/pdf' => 'pdf',
    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'audio/mpeg' => 'mp3', 'video/mpeg' => 'mpeg',
    'audio/ogg' => 'ogg', 'video/ogg' => 'ogv', 'text/plain' => 'txt', 'text/csv' => 'csv',
    'text/markdown' => 'md', 'font/woff2' => 'woff2'];

  /* The @type an asset leaves under, by the first half of its MIME type, and
     what `Claims` checks a property against. Any other record leaves as itself. */
  public const EMITTED = ['image' => 'ImageObject', 'video' => 'VideoObject', 'audio' => 'AudioObject'];

  public static function emittedType(Entity $entity): string
  {
    if ($entity->typeName() !== 'MediaObject') {
      return $entity->typeName();
    }
    $format = (string) $entity->props->string('encoding_format');

    return self::EMITTED[strtok($format, '/')] ?? 'MediaObject';
  }

  public function __construct(
    private Base $base = new Base(),
    private Fields $f = new Fields(),
    private ValueObjects $vo = new ValueObjects(),
  ) {}

  /** @return list<TypeDefinition> */
  public function types(): array
  {
    return [
      new TypeDefinition('MediaObject', Erasure::Purge, LocaleMode::Both, Reach::Public,
        $this->mediaObject(),
        [...$this->base->creativeWork(LocaleMode::Both), ...$this->base->thing()]),
    ];
  }

  /** @return list<FieldDefinition> */
  private function mediaObject(): array
  {
    $f = $this->f;

    return [
      $f->link('content_url', 'File address')->required(),
      $f->text('encoding_format', 'MIME type')->starter(),
      $f->text('content_size', 'File size'),
      $f->text('sha256', 'SHA-256'),
      /* Pixels, so no unit field: an amount carries `unitCode` only where a house can
         vary the unit, and an image's dimensions are counted in one thing. */
      $f->number('width', 'Width')->keyOf('QuantitativeValue', 'value', 'width'),
      $f->number('height', 'Height')->keyOf('QuantitativeValue', 'value', 'height'),
      $f->text('caption', 'Caption')->starter()->words(),
      $f->text('embedded_text_caption', 'Embedded caption')->words(),
      $f->boolean('representative_of_page', 'Representative of the page'),
      $f->objects('exif_data', 'EXIF', 'PropertyValue'),
      $f->dateTime('upload_date', 'Uploaded')->starter(),
      $f->link('acquire_license_page', 'Licence page'),
      $f->text('credit_text', 'Credit')->starter()->words(),
      $f->duration('duration', 'Running time'),
      $f->longText('transcript', 'Transcript'),
      $f->text('video_quality', 'Video quality'),
      $f->text('video_frame_size', 'Frame size'),
      $f->text('bitrate', 'Bitrate'),
      /* Clip value objects, not edges — this type's own reading of hasPart. */
      $f->objects('has_part', 'Clips', 'Clip'),
      $f->term('regions_allowed', 'Regions allowed', 'region')->repeatable(),
      $f->term('ineligible_region', 'Regions excluded', 'region')->repeatable(),
      $f->boolean('requires_subscription', 'Requires subscription'),
      /* An authored poster somebody chose and uploaded — the generated preview
         is derived, lives at a deterministic address, and is stored nowhere. */
      $f->ref('thumbnail', 'Poster', 'MediaObject'),
      $f->refs('actor', 'Actor', 'Person'),
      $f->refs('director', 'Director', 'Person'),
      $f->refs('creator', 'Creator', 'Person', 'Organization'),
      $f->ref('production_company', 'Production company', 'Person', 'Organization'),
    ];
  }
}
