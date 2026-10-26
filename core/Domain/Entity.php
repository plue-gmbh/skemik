<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\ForbiddenTransition;
use Domain\Exceptions\MalformedValue;
use Domain\Exceptions\UnknownType;
use Profile\TypeDefinition;

/* Spine plus props, one `entity` row. Knows its type name but not its
   `TypeDefinition`: validation is done to it, not by it. What it refuses is what
   no type definition could make right — a state its type cannot hold, a window
   that closes before it opens, an uploaded file repointed by an edit, an instant not in the one stored spelling, which
   `Status::isPublicAt` compares as text. Version and the `updated_` stamps are
   written by `Storage\EntityRepository::save`, never by a `with` here, because a
   version bumped twice for one save is an `If-Match` that can never succeed;
   `created_at` is the record's own, which only a replaced file moves. */
final readonly class Entity
{
  private const SCHEMA_ORG = 'https://schema.org/';
  private const FILE = ['content_url', 'encoding_format'];
  private const INSTANT = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

  public function __construct(
    public EntityId $id,
    public string $type,
    public Locale $locale,
    public Slug $slug,
    public Props $props,
    public string $createdAt,
    public string $updatedAt,
    public Status $status = Status::Draft,
    public ?EntityId $translationOf = null,
    public int $version = 1,
    public ?string $publishFrom = null,
    public ?string $publishTo = null,
    public ?string $deletedAt = null,
  ) {
    if (preg_match('/^[A-Z][A-Za-z]+$/', $this->typeName()) !== 1) {
      throw UnknownType::named($type);
    }
    if ($version < 1) {
      throw MalformedValue::named('version', (string) $version);
    }
    $this->checkInstants([$createdAt, $updatedAt, $publishFrom, $publishTo, $deletedAt]);
  }

  public function typeName(): string
  {
    if (!str_starts_with($this->type, self::SCHEMA_ORG)) {
      return '';
    }

    return substr($this->type, strlen(self::SCHEMA_ORG));
  }

  /* What a save would write, the bookkeeping columns being the save's own. */
  public function holdsAs(Entity $stored): bool
  {
    return $this->props->document() === $stored->props->document() && $this->spineChanged($stored) === []
      && $this->slug->value === $stored->slug->value
      && $this->translationOf?->value === $stored->translationOf?->value;
  }

  /* The spine's settings a save would change, by the spine dialog's keys; the
     slug is a former address the log keeps whole, and a translation is joined
     on a screen of its own.
     @return list<string> */
  public function spineChanged(Entity $stored): array
  {
    return array_keys(array_filter([
      'status' => $this->status !== $stored->status,
      'publish_from' => $this->publishFrom !== $stored->publishFrom,
      'publish_to' => $this->publishTo !== $stored->publishTo,
      'locale' => $this->locale->tag !== $stored->locale->tag,
    ]));
  }

  public function withProps(Props $props): self
  {
    $edited = clone($this, ['props' => $props]);
    foreach (self::FILE as $key) {
      $changed = ($props->document()[$key] ?? null) !== ($this->props->document()[$key] ?? null);
      if ($changed && ($this->holdsFile() || $edited->holdsFile())) {
        throw ForbiddenTransition::fileKept($key);
      }
    }

    return $edited;
  }

  /* A file of this house keeps the address and the type its upload wrote, since
     the bytes are found by both, and no edit claims one: `Admin\Intake` is the
     one writer of a `/media/` path, through `withFile`. */
  public function keeps(string $key): bool
  {
    return in_array($key, self::FILE, true) && $this->holdsFile();
  }

  /* `Admin\Intake`'s repointing: a new file at a new address, so the bytes at
     an address still never change. A replaced file is a new upload, so the
     record is made anew at `$at` and orders as one, its log keeping when it was first. */
  public function withFile(Props $props, string $at): self
  {
    $edited = clone($this, ['props' => $props, 'createdAt' => $at]);
    $edited->checkInstants([$at]);

    return $edited->holdsFile() ? $edited : throw ForbiddenTransition::fileKept('content_url');
  }

  private function holdsFile(): bool
  {
    $address = $this->props->document()['content_url'] ?? null;

    return is_string($address) && str_starts_with($address, '/media/');
  }

  /* The one place a status changes. A stored row is not asked here, having no
     type in reach: `Storage\HoldingsCheck` asks it. */
  public function withStatus(Status $next, TypeDefinition $type): self
  {
    assert($type->name === $this->typeName(), 'a status is held by the record\'s own type');

    return clone($this, ['status' => $this->status->to($next, $type)]);
  }

  public function withSlug(Slug $slug): self
  {
    return clone($this, ['slug' => $slug]);
  }

  public function withLocale(Locale $locale): self
  {
    return clone($this, ['locale' => $locale]);
  }

  public function withTranslationOf(?EntityId $root): self
  {
    return clone($this, ['translationOf' => $root]);
  }

  /* A clone skips the constructor, so the window is checked here as it is there. */
  public function withWindow(?string $publishFrom, ?string $publishTo): self
  {
    $next = clone($this, ['publishFrom' => $publishFrom, 'publishTo' => $publishTo]);
    $next->checkInstants([$publishFrom, $publishTo]);

    return $next;
  }

  /** @param list<?string> $instants */
  private function checkInstants(array $instants): void
  {
    foreach ($instants as $instant) {
      if ($instant !== null && preg_match(self::INSTANT, $instant) !== 1) {
        throw MalformedValue::named('stored instant', $instant);
      }
    }
    $window = [$this->publishFrom, $this->publishTo];
    if (!in_array(null, $window, true) && $this->publishTo <= $this->publishFrom) {
      throw MalformedValue::named('publication window', $this->publishFrom . ' – ' . $this->publishTo);
    }
  }
}
