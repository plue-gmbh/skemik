<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\Locale;
use Domain\LocalisedText;
use Http\Request;
use Profile\Cardinality;
use Profile\Profile;
use Storage\EntityRepository;
use Storage\SettingStore;
use Support\Words;

/* A labelled thing's record screen in a house locale other than the one its
   words were written in, named by `words`. Read draws the record as that
   locale's route serves it; edit offers the translatable fields alone, each with
   that locale's own words, and an empty one shows the authored words greyed,
   since that is what the feed serves there and what emptying returns it to. A
   reference holding edges is offered for what they say there, its targets being
   the authored record's to change. On a type that also forks, a locale its
   group holds a record in belongs to that record, so the switch leaves it out. */
final readonly class RecordWords
{
  public function __construct(
    private Sheet $sheet,
    private EntityRepository $entities,
    private SettingStore $settings,
    private Profile $profile,
    private Words $words,
  ) {}

  /** @return list<Locale> the switch, the authored locale first; empty where there is nothing to switch to */
  public function offered(Entity $entity): array
  {
    $mode = $this->profile->byType($entity->type)->locale;
    if (!$mode->localises()) {
      return [];
    }
    $group = $mode->forks() ? $this->entities->group($entity) : null;
    $others = array_filter($this->settings->locales(), fn (Locale $locale): bool =>
      $locale->tag !== $entity->locale->tag && $group?->inLocale($locale) === null);

    return $others === [] ? [] : [$entity->locale, ...array_values($others)];
  }

  /* Null for the authored words, which is also what an unknown `words` opens. */
  public function locale(Request $request, Entity $entity): ?Locale
  {
    $named = array_find($this->offered($entity),
      fn (Locale $locale): bool => $locale->tag === $request->query('words'));

    return $named?->tag === $entity->locale->tag ? null : $named;
  }

  /**
   * The row read draws, its references links, the row edit draws, and the columns edit offers.
   *
   * @param list<Column> $columns
   * @return array{SheetRow, SheetRow, list<Column>}
   */
  public function rows(Account $account, array $columns, Entity $entity, ?Locale $locale): array
  {
    $reading = array_map(fn (Column $column): Column => $column->readOnly(), $columns);
    if ($locale === null) {
      return [$this->sheet->rows($account, $reading, [$entity])[0],
        $this->sheet->rows($account, $columns, [$entity])[0], $columns];
    }
    $localised = new LocalisedText($this->profile->byType($entity->type));
    /* Drawn as that locale reads it, a select's word and a target's name
       included, while the row still names the record as it is written. */
    $drawn = fn (array $shownColumns, Entity $shown): SheetRow
      => $this->sheet->rows($account, $shownColumns, [$shown->withLocale($locale)])[0]->withEntity($shown);
    $read = $drawn($reading, $localised->shown($entity, $locale));
    $editing = array_values(array_filter($columns, fn (Column $column): bool => $column->editable
      && (in_array($column->field->key, $localised->fields(), true)
        || ($column->field->cardinality === Cardinality::E && ($read->chips[$column->field->key] ?? []) !== []))));
    $own = $drawn($editing, $localised->own($entity, $locale));

    return [$read, $this->hinted($own, $read, $entity, $locale), $editing];
  }

  /* An empty field reads as the authored words, greyed, and says so to a screen
     reader; its editor holds them as its placeholder. A reference reads as that
     locale's route reads it, its targets being the same in every locale. */
  private function hinted(SheetRow $own, SheetRow $read, Entity $authored, Locale $locale): SheetRow
  {
    $said = $this->words->get('record.words_hint', ['locale' => $authored->locale->tag]);
    $hint = '<span class="cell-hint"><span class="visually-hidden">'
      . htmlspecialchars($said, ENT_QUOTES, 'UTF-8') . '</span>';
    $cells = [];
    foreach ($own->cells as $key => $cell) {
      $cells[$key] = $own->entity->props->has($key) || isset($own->chips[$key]) ? $cell
        : $hint . $read->cells[$key] . '</span>';
    }

    return new SheetRow($own->entity, $read->label, $own->state, $own->reach, $cells, $own->own, $own->chips, $authored,
      $own->changed, $locale);
  }
}
