<?php

declare(strict_types=1);

namespace Profile;

use Domain\Violation;

/* What the page says of the profile's own shape, each true with no data, no
   request and no vocabulary: the half of `Claims` that asks the profile about
   itself. A promise here is one a release could break by editing the seed and
   nothing else, which is why it is checked where the seed is read and not by
   walking an install. */
final readonly class Promises
{
  /* What a house transacts, and the field that says how each stands. */
  public const STANDING = ['Order' => 'order_status', 'Invoice' => 'payment_status',
    'Reservation' => 'reservation_status', 'Event' => 'event_status', 'Action' => 'action_status'];

  /* Every column of a row, and `l10n` beside the fields in `props`. A field of
     one of these names would be sorted and filtered as the column: an offer's
     `validFrom` once was, while the window was spelled like it. */
  private const SPINE = ['id', 'type', 'status', 'locale', 'translation_of', 'slug',
    'version', 'publish_from', 'publish_to', 'created_at', 'updated_at', 'deleted_at', 'sort_keys', 'props',
    'words', 'l10n'];

  public function __construct(private Profile $profile) {}

  /** @return array<string, list<Violation>> */
  public function checks(): array
  {
    return [
      'lists' => $this->checkLists(),
      'terms' => $this->checkTerms(),
      'transactions' => $this->checkTransactions(),
      'spine' => $this->checkSpine(),
    ];
  }

  /* A list replaces a custom collection only while its members may be anything
     and their order is the edge's. @return list<Violation> */
  private function checkLists(): array
  {
    $members = $this->field('ItemList', 'item_list_element');
    $holds = $members?->kind === FieldKind::Refs && $members->targets === ['*'];

    return $holds ? [] : [Violation::stated('ItemList.item_list_element', 'not an ordered edge to any type')];
  }

  /* A vocabulary is data only while every controlled value is a term and a term
     is found by its code within its set. @return list<Violation> */
  private function checkTerms(): array
  {
    $code = $this->field('DefinedTerm', 'term_code');
    $violations = $code?->unique?->edge === 'in_defined_term_set' ? []
      : [Violation::stated('DefinedTerm.term_code', 'not unique within its set')];
    foreach ($this->profile->types() as $type) {
      foreach ($type->fields() as $field) {
        if ($field->cardinality === Cardinality::T && $field->targets !== ['DefinedTerm']) {
          $violations[] = Violation::stated($type->name . '.' . $field->key,
            'a controlled value that is no term');
        }
      }
    }

    return $violations;
  }

  /* No second system for what a house transacts: each says how it stands in a
     closed list of its own. @return list<Violation> */
  private function checkTransactions(): array
  {
    $violations = [];
    foreach (self::STANDING as $type => $key) {
      $status = $this->field($type, $key);
      if ($status?->kind !== FieldKind::Select || $status->options === []) {
        $violations[] = Violation::stated($type . '.' . $key, 'no closed list saying how the record stands');
      }
    }

    return $violations;
  }

  /* Address, state and language sit in one place on all thirty only while no
     type declares a field under a spine column's name. @return list<Violation> */
  private function checkSpine(): array
  {
    $violations = [];
    foreach ($this->profile->types() as $type) {
      foreach ($type->fields() as $field) {
        if (in_array($field->key, self::SPINE, true)) {
          $violations[] = Violation::stated($type->name . '.' . $field->key, 'takes the name of a spine column');
        }
      }
    }

    return $violations;
  }

  private function field(string $type, string $key): ?FieldDefinition
  {
    return array_find($this->profile->types(),
      fn (TypeDefinition $candidate): bool => $candidate->name === $type)?->field($key);
  }
}
