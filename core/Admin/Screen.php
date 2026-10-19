<?php

declare(strict_types=1);

namespace Admin;

/* What one admin screen brings to its frame (`Admin\Frame`): its title, its
   paper, the address its feed panel shows, the sidebar entry it holds open,
   whether it starts an island, the sentence it opens with, and the sidebar's
   entries where it has read them for its own lines already, so they are not
   counted twice. */
final readonly class Screen
{
  /** @param ?list<SidebarEntry> $entries */
  public function __construct(
    public string $title,
    public string $main,
    public FeedPanel $feed,
    public ?string $open = null,
    public bool $islands = false,
    public string $notice = '',
    public ?array $entries = null,
  ) {}
}
