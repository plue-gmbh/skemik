<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;
use Profile\Predicate;
use Profile\Qualifier;

/* One `relation` row: subject, predicate, object, optional position, optional
   qualifiers. Ordered relations carry `position`; that is how the main menu, the
   breadcrumb and the curated rail are all one mechanism.
   The predicate and the closed qualifier set (`Profile\Qualifier`) are refused
   here, on the value both doors construct, because `Ingest\PayloadMapper` held the
   set on the machine door and nothing held it on the editor's. What the target is travels with the edge:
   its type name, because the membership keys depend on it, `Feed\JsonLd` picks the
   wrapper by it and `Profile\EdgeValidator` checks it against the field; and on a
   `DefinedTerm` its `termCode`, which is what a term field stores, validates and
   emits. */
final readonly class Relation
{
  public function __construct(
    Predicate $predicates,
    public EntityId $subject,
    public string $predicate,
    public EntityId $object,
    public string $objectType,
    public ?string $objectTerm = null,
    public ?int $position = null,
    public Props $qualifiers = new Props([]),
  ) {
    $predicates->require($predicate);
    if (($objectType === 'DefinedTerm') !== ($objectTerm !== null)) {
      throw MalformedValue::named('term code on ' . $predicate, (string) $objectTerm);
    }
    if ($position !== null && $position < 1) {
      throw MalformedValue::named('position', (string) $position);
    }
    foreach (array_keys($qualifiers->document()) as $key) {
      $qualifier = Qualifier::tryFrom((string) $key);
      $held = $key === LocalisedText::KEY || ($qualifier !== null
        && (!$qualifier->ofMembership() || Qualifier::membership($predicate, $objectType)));
      if (!$held) {
        throw MalformedValue::named(sprintf('qualifier on %s → %s', $predicate, $objectType), (string) $key);
      }
    }
  }

  /* A clone skips the constructor: only the qualifiers of an edge already built,
     rearranged by locale, pass through here. */
  public function withQualifiers(Props $qualifiers): self
  {
    return clone($this, ['qualifiers' => $qualifiers]);
  }

  /* The words as that locale reads them: its own where it has some, the ones the
     edge was written with where not, as a labelled thing's are read.
     @return array<string, mixed> */
  public function qualifiersIn(Locale $locale): array
  {
    return [...array_diff_key($this->qualifiers->document(), [LocalisedText::KEY => true]),
      ...$this->wordsIn($locale)];
  }

  /* The words that locale holds of its own, and none where it holds none.
     @return array<string, mixed> */
  public function wordsIn(Locale $locale): array
  {
    $own = $this->qualifiers->document()[LocalisedText::KEY][$locale->tag] ?? [];

    return is_array($own) ? $own : [];
  }
}
