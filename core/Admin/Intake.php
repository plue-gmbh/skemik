<?php

declare(strict_types=1);

namespace Admin;

use Auth\UnwritableDirectory;
use Domain\Entity;
use Domain\EntityId;
use Domain\Props;
use Domain\Relation;
use Domain\Slug;
use Domain\SvgGuard;
use Domain\Violation;
use Http\Upload;
use Profile\Profile;
use Profile\Seed\Media;
use Profile\Validator;
use Storage\Collection;
use Storage\EntityRepository;
use Storage\Query;
use Storage\SettingStore;
use Support\Clock;
use Support\Thumbnail;
use Support\Uuid;
use Support\Words;

/* What one upload becomes: an asset of the library, or a sentence naming the
   file and why not. The type is sniffed and the browser's claim discarded. A
   file is stored under a generated name and its address is that name, so the
   bytes at an address never change: replacing an asset is a new file and a
   repointed `contentUrl`, and the old bytes leave with the last row holding
   them, as a purge's do. The address is a path, so a house that moves its
   domain keeps every asset. The file is taken with `move_uploaded_file` at once;
   before that it sat in `upload_tmp_dir`, which is the host's and no `ini_set`
   moves. One door for every file, the library's and a channel's
   (`Ingest\Filed`), so a file is checked one way. */
final readonly class Intake
{
  public const ADDRESS = '/media/';
  private const LOCK = "Require all denied\n<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n";
  /* What `described` writes, taken whole by a replacement, since a width the new
     file lacks is not the old one's; the name only where it is the old file's (`named`). */
  private const DESCRIBED = ['content_url', 'encoding_format', 'content_size', 'sha256', 'upload_date',
    'width', 'height'];

  public function __construct(
    private EntityRepository $entities,
    private Validator $validator,
    private Profile $profile,
    private SettingStore $settings,
    private Uuid $uuid,
    private Clock $clock,
    private Words $words,
    private Thumbnail $thumbnail,
    private string $directory,
    private SvgGuard $guard = new SvgGuard(),
  ) {}

  /* As a draft, like every other record; a field's picker takes what it offers (`SchemaOrg::fileKinds`).
     @param list<string> $kinds */
  public function taken(Collection $library, ?Upload $upload, array $kinds = []): Entity
  {
    $file = $this->file($upload, $kinds);
    if ($this->validator->check($this->profile->byType($library->type), $file->document(), []) !== []) {
      $this->drop($file);
      throw new \LogicException('an upload the profile refuses: ' . $upload?->name);
    }
    $now = $this->clock->now()->format(Clock::STORED);

    return $this->entities->save(new Entity(new EntityId($this->uuid->generate()),
      $library->type, $this->settings->locales()[0], Slug::from((string) $upload?->name, 'file'), $file, $now,
      $now), []);
  }

  /* The record's file exchanged for this one, taken by `file`, its other fields and its edges kept.
     @param list<Relation> $edges the asset's, as they stand */
  public function replaced(Entity $asset, Props $file, array $edges): Entity
  {
    $kept = array_diff_key($asset->props->document(), array_flip(self::DESCRIBED));
    $named = self::fileName((string) $asset->props->string('name'),
      (string) $asset->props->string('encoding_format'));
    $described = $named ? $file->document() : array_diff_key($file->document(), ['name' => true]);
    $replaced = $asset->withFile(new Props([...$kept, ...$described]), (string) $file->string('upload_date'));
    $type = $this->profile->byType($asset->type);
    if ($this->validator->check($type, $replaced->props->document(), $edges) !== []) {
      $this->drop($file);
      throw new \LogicException('a replacement the profile refuses: ' . $file->string('name'));
    }
    try {
      return $this->entities->save($replaced, $edges, $asset->version);
    } catch (\Throwable $failed) {
      $this->drop($file);
      throw $failed;
    }
  }

  /* Whether an asset's name is a file's, ending in its format's extension as an
     upload names it, `.jpg` or `.jpeg`: such a name follows a new file, and a
     name typed by hand stays and is downloaded with the extension it lacks. */
  public static function fileName(string $name, string $format): bool
  {
    $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

    return $extension !== ''
      && in_array($extension, [Media::FORMATS[$format] ?? null, substr((string) strrchr($format, '/'), 1)], true);
  }

  /* The bytes and the preview of a row purged or repointed, unless another row,
     trashed ones counted, still holds them. */
  public function release(Entity $asset): void
  {
    $name = basename((string) $asset->props->string('content_url'));
    $held = new Query($asset->type, ['content_url' => [self::ADDRESS . $name]], perPage: 1);
    if (!$asset->keeps('content_url') || preg_match(Uuid::SHAPE, $name) !== 1
      || $this->entities->count($held) + $this->entities->count($held->inTrash()) > 0) {
      return;
    }
    $files = glob($this->directory . '/' . $name . '.*');
    array_map(unlink(...), $files === false ? [] : $files);
    $this->thumbnail->discard($name);
  }

  /* The file placed under a generated name and described as its asset holds it,
     or refused in a sentence naming it, and where a kind of file is shown,
     refused unless it is one, before anything is placed.
     @param list<string> $kinds the kinds taken, `image`, none for any */
  public function file(?Upload $upload, array $kinds = []): Props
  {
    $mime = $upload?->error === UPLOAD_ERR_OK && class_exists(\finfo::class)
      ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($upload->path) : '';
    $refusal = $this->refusal($upload, $mime, $kinds);
    if ($refusal !== null || $upload === null) {
      throw UploadRefused::because((string) $refusal);
    }

    return new Props($this->described($upload, $mime, $this->placed($upload, $mime)));
  }

  /* A placed file whose asset was not written, so no file is left that no record holds. */
  public function drop(Props $file): void
  {
    $format = Media::FORMATS[(string) $file->string('encoding_format')] ?? '';
    $path = $this->directory . '/' . basename((string) $file->string('content_url')) . '.' . $format;
    if ($format !== '' && is_file($path)) {
      unlink($path);
    }
  }

  /* Counted as PHP's own `2M` counts, so the cap reads as the hoster's setting does. */
  public static function size(int $bytes): string
  {
    $rounded = fn (int $power): string => rtrim(rtrim(number_format($bytes / (1 << $power), 1, '.', ''), '0'), '.');

    return match (true) {
      $bytes < 1 << 20 => max(1, (int) round($bytes / 1024)) . ' KB',
      $bytes < 1 << 30 => $rounded(20) . ' MB',
      default => $rounded(30) . ' GB',
    };
  }

  /* Why this file is not taken, in a sentence naming it; null where it is.
     @param list<string> $kinds */
  private function refusal(?Upload $upload, string $mime, array $kinds): ?string
  {
    $said = ['name' => $upload?->name ?? '', 'limit' => self::size(Upload::limit()), 'type' => $mime];

    return match (true) {
      $upload === null, $upload->error === UPLOAD_ERR_NO_FILE => $this->words->get('media.none', $said),
      in_array($upload->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true),
      $upload->size > Upload::limit() => $this->words->get('media.too_large', $said),
      $upload->error !== UPLOAD_ERR_OK, !is_uploaded_file($upload->path) => $this->words->get('media.failed', $said),
      !class_exists(\finfo::class) => $this->words->get('boot.extension', ['extension' => 'fileinfo']),
      !isset(Media::FORMATS[$mime]) => $this->words->get('media.format', $said),
      $kinds !== [] && !in_array(strtok($mime, '/'), $kinds, true)
        => $this->words->get('media.no_' . $kinds[0], $said),
      $mime === 'image/svg+xml' => $this->drawing($upload, $said),
      default => null,
    };
  }

  /** @param array<string, string> $said */
  private function drawing(Upload $upload, array $said): ?string
  {
    $violations = $this->guard->check((string) file_get_contents($upload->path));
    $reasons = implode('; ', array_map(fn (Violation $violation): string => $violation->reason, $violations));

    return $violations === [] ? null : $this->words->get('media.drawing', [...$said, 'reasons' => $reasons]);
  }

  /* Under a generated name; the tree is created on the first upload, with its second lock in it. */
  private function placed(Upload $upload, string $mime): string
  {
    if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true)) {
      throw UnwritableDirectory::named($this->directory);
    }
    is_file($this->directory . '/.htaccess') || file_put_contents($this->directory . '/.htaccess', self::LOCK);
    $path = $this->directory . '/' . $this->uuid->generate() . '.' . Media::FORMATS[$mime];

    return move_uploaded_file($upload->path, $path) ? $path : throw UnwritableDirectory::named($this->directory);
  }

  /** @return array<string, string|int> */
  private function described(Upload $upload, string $mime, string $path): array
  {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $raster = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
    $size = $raster ? getimagesize($path) : false;
    $file = trim($upload->name);

    return ['name' => $file === '' ? $name : $file, 'content_url' => self::ADDRESS . $name,
      'encoding_format' => $mime, 'content_size' => self::size((int) filesize($path)),
      'sha256' => (string) hash_file('sha256', $path),
      'upload_date' => $this->clock->now()->format(Clock::STORED),
      ...($size === false ? [] : ['width' => $size[0], 'height' => $size[1]])];
  }
}
