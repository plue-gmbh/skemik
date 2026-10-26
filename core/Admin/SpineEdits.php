<?php

declare(strict_types=1);

namespace Admin;

use Domain\Entity;
use Domain\EntityId;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\LocalisedText;
use Domain\Slug;
use Domain\Status;
use Domain\Violation;
use Profile\Profile;
use Storage\Collisions;
use Storage\EntityRepository;
use Storage\SettingStore;
use Support\Clock;
use Support\Words;

/* The spine dialog's pending edits (`partials/spine-dialog`), applied as part of
   a save's `PATCH`. The slug is slugified again by `Domain\Slug`, whatever the
   screen made of it. The locale moves on a work only: a labelled thing's locale
   names the words it was authored in, and moving it would relabel them.
   What the dialog cannot prevent comes back as a violation on the `spine.` key it
   names: a slug its locales already hold and a locale its group already has,
   each in the trash too and saying so, and on a type with both mechanisms a
   locale its own words are already kept in, since the authored words would then
   claim a language another set of them holds. Anything else malformed is a request no screen sends, and
   is refused whole. */
final readonly class SpineEdits
{
  private const KEYS = ['slug', 'status', 'publish_from', 'publish_to', 'locale'];

  public function __construct(
    private Collisions $collisions,
    private EntityRepository $entities,
    private Profile $profile,
    private SettingStore $settings,
    private Clock $clock,
    private Words $words,
  ) {}

  /* Decoded JSON, before anything has read it: `mixed` is honest here. */
  public function applied(Entity $entity, mixed $sent): Entity
  {
    if ($sent === null) {
      return $entity;
    }
    if (!is_array($sent) || array_is_list($sent) || array_diff(array_keys($sent), self::KEYS) !== []) {
      throw MalformedValue::notA('an object of spine edits', 'spine');
    }
    $slug = array_key_exists('slug', $sent) ? Slug::from($this->text($sent['slug']), $entity->slug->value) : null;
    $status = array_key_exists('status', $sent) ? Status::tryFrom($this->text($sent['status'])) : $entity->status;
    $edited = $entity->withSlug($slug ?? $entity->slug)
      ->withStatus($status ?? throw MalformedValue::named('status', $this->text($sent['status'])),
        $this->profile->byType($entity->type))
      ->withWindow($this->instant($sent, 'publish_from', $entity->publishFrom),
        $this->instant($sent, 'publish_to', $entity->publishTo));

    return array_key_exists('locale', $sent) ? $edited->withLocale($this->locale($entity, $sent['locale'])) : $edited;
  }

  /** @return list<Violation> */
  public function violations(Entity $stored, Entity $edited): array
  {
    $violations = [];
    $type = $this->profile->byType($edited->type);
    $moved = $stored->locale->tag !== $edited->locale->tag;
    if ($moved && in_array($edited->locale->tag, (new LocalisedText($type))->locales($edited), true)) {
      $violations[] = new Violation('spine.locale', $this->words->get('spine.locale_worded'));
    }
    $violations[] = $this->heldBy('slug', $this->collisions->slugHolder($edited));
    $violations[] = $this->heldBy('locale',
      $type->locale->forks() ? $this->collisions->translationHolder($edited) : null);

    return array_values(array_filter($violations));
  }

  private function heldBy(string $key, ?EntityId $holder): ?Violation
  {
    if ($holder === null) {
      return null;
    }
    $trashed = $this->entities->find($holder)->deletedAt !== null;

    return new Violation('spine.' . $key, $this->words->get('spine.' . $key . ($trashed ? '_trashed' : '_taken')));
  }

  /* The group's member in the locale a translation is asked in, to open in
     place of a second one. One in the trash is asked of the collision the save
     asks, which refuses it naming the trash: `EntityRepository::group` leaves
     the trash out, and a save counts it. */
  public function member(Entity $entity, Locale $locale): ?Entity
  {
    $holder = $this->collisions->translationHolder($entity->withLocale($locale));
    $member = $holder === null ? null : $this->entities->find($holder);
    if ($member?->deletedAt !== null) {
      $this->collisions->requireFreeTranslation($entity->withLocale($locale));
    }

    return $member;
  }

  /** @param array<array-key, mixed> $sent */
  private function instant(array $sent, string $key, ?string $stored): ?string
  {
    if (!array_key_exists($key, $sent)) {
      return $stored;
    }

    return $sent[$key] === null || $sent[$key] === '' ? null : $this->clock->parse($this->text($sent[$key]));
  }

  /* Also the locale a translation is made in: on a work, and one the house holds. */
  public function locale(Entity $entity, mixed $sent): Locale
  {
    if (!$this->profile->byType($entity->type)->locale->forks()) {
      throw MalformedValue::named('type whose language is its record', $entity->typeName());
    }
    $tag = $this->text($sent);

    return array_find($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw MalformedValue::named('locale of this house', $tag);
  }

  private function text(mixed $value): string
  {
    return is_string($value) ? $value : throw MalformedValue::notA('text', 'spine');
  }
}
