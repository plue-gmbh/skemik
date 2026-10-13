<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;
use Profile\TypeDefinition;

/* The first of the spine's two language mechanisms, for the types whose `LocaleMode` forks —
   the works, and the two that fork when their bytes differ — where a translation
   is its own record with its own slug and lifecycle. Every member's `translation_of` names the root, never another
   translation, so a group is one lookup and not a walk. It asks the type
   definition rather than holding a list, refuses two members in one locale,
   and answers which members a group has so `Feed\JsonLd` can emit
   `translationOfWork` / `workTranslation` to each of the others. */
final readonly class TranslationGroup
{
  public EntityId $root;

  /** @param non-empty-list<Entity> $members */
  public function __construct(private TypeDefinition $type, public array $members)
  {
    if (!$type->locale->forks()) {
      throw MalformedValue::named('type that forks', $type->name);
    }
    $this->root = self::rootOf($members[0]);
    $locales = [];
    foreach ($members as $member) {
      assert($member->typeName() === $type->name, 'a group holds one type');
      if (self::rootOf($member)->value !== $this->root->value) {
        throw MalformedValue::named('member of group ' . $this->root->value, $member->id->value);
      }
      if (isset($locales[$member->locale->tag])) {
        throw MalformedValue::named('free locale in this group', $member->locale->tag);
      }
      $locales[$member->locale->tag] = true;
    }
  }

  public static function rootOf(Entity $entity): EntityId
  {
    return $entity->translationOf ?? $entity->id;
  }

  public function with(Entity $member): self
  {
    return new self($this->type, [...$this->members, $member]);
  }

  public function inLocale(Locale $locale): ?Entity
  {
    foreach ($this->members as $member) {
      if ($member->locale->tag === $locale->tag) {
        return $member;
      }
    }

    return null;
  }

  /** @return list<Entity> */
  public function others(Entity $member): array
  {
    $isOther = fn (Entity $candidate): bool => $candidate->id->value !== $member->id->value;

    return array_values(array_filter($this->members, $isOther));
  }
}
