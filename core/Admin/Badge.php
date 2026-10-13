<?php

declare(strict_types=1);

namespace Admin;

/* One grant badge: its anchor — the collection, one field of it or one record —
   the grants reviewed there, and for `manageTokens` what the popover offers to
   issue. The capability follows the anchor: a collection proposes `create` with
   `read` beside it, a field or a record proposes `set`. */
final readonly class Badge
{
  /**
   * @param list<GrantReview> $reviews
   * @param list<GrantOffer> $offers none where the reader may not issue
   * @param ?string $subject the record's id where the anchor is one record
   */
  public function __construct(
    public ?string $field,
    public string $title,
    public array $reviews,
    public array $offers,
    public ?string $subject = null,
  ) {}

  /* The keys holding a grant here, each once however many rules it holds. */
  public function names(): string
  {
    return implode(', ', array_unique(array_map(fn (GrantReview $review): string => $review->token->label,
      $this->reviews)));
  }
}
