<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Profile\Profile;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Support\Markup;

/* The record screen's reading of a Markdown field: `Support\Markup`'s, under
   the screen's own headings, and the one addition is the house's own link:
   `skemik:` and an id, which survives a slug change because it names the
   record. It becomes a chip that opens that record, or ⇢ and its text struck
   through where the record is gone or not this reader's to see — the same
   answer, so the page never says which. */
final readonly class Prose
{
  public function __construct(
    private EntityRepository $entities,
    private CollectionStore $collections,
    private Rows $rows,
    private Profile $profile,
    private Label $label = new Label(),
  ) {}

  public function markdown(Account $account, string $source): string
  {
    $link = fn (string $text, string $address): string => str_starts_with($address, Markup::RECORD)
      ? $this->record($account, $text, substr($address, strlen(Markup::RECORD)))
      : Markup::anchor($text, $address);

    return (new Markup($link))->prose($source)->html;
  }

  public function text(string $source): string
  {
    return (new Markup())->text($source)->html;
  }

  private function record(Account $account, string $text, string $id): string
  {
    try {
      $target = $this->entities->find(new EntityId($id));
    } catch (EntityNotFound | MalformedValue) {
      $target = null;
    }
    $holder = $target === null ? null : array_find($this->collections->all(),
      fn (Collection $collection): bool => $collection->type === $target->type);
    if ($target === null || $holder === null || $target->deletedAt !== null
      || !$this->rows->readable($account, $target)) {
      return '<span class="chip is-gone"><s>⇢ ' . $text . '</s></span>';
    }
    $title = $holder->name . ' · ' . $this->label->of($target, $this->profile->byType($target->type));

    return sprintf('<a class="chip" href="%s" title="%s"><span class="chip-mark"><svg class="glyph"'
      . ' aria-hidden="true"><use href="#glyph-%s"></use></svg></span>%s</a>',
      $this->escape(Rows::address($holder, $target)), $this->escape($title),
      $this->escape(SidebarEntry::glyphOf($target->type)), $text);
  }

  private function escape(string $text): string
  {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }
}
