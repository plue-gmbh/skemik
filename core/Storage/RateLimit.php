<?php

declare(strict_types=1);

namespace Storage;

use Support\Clock;
use Support\Uuid;

/* One bucket counter over `rate_limit`, for the login path and the ingest path
   alike: one table, because two mechanisms would drift. Each caller builds its
   own with its own allowance, since a till posting orders is not a stranger
   guessing codes. A refusal ends with its window and never lasts longer:
   a lockout keyed on an address anyone may type is a denial of service aimed at
   the owner. The window slides, in slices, because one that turns with the clock
   lets twice its allowance through around the turn. A slice counts until the
   window has wholly passed it, so the limit errs towards refusing a slice early
   and never towards letting more through. A row holds when it stops counting
   rather than when it began, so any hit deletes every ended row of every bucket,
   whatever length the caller chose: an address typed once is gone with the next
   attempt anywhere, nothing prunes on a schedule and nothing else reads the rows. */
final readonly class RateLimit
{
  private const SLICES = 12;

  public function __construct(
    private \PDO $db,
    private Uuid $uuid,
    private Clock $clock,
    private int $attempts,
    private int $seconds,
  ) {
    assert($attempts >= 1 && $seconds >= 1, 'a limit that allows nothing is a lockout');
  }

  /* Counted whether or not it is allowed, so hammering a refused bucket does not
     earn it a fresh allowance sooner. */
  public function hit(string $bucket): bool
  {
    $instant = $this->clock->now()->getTimestamp();
    $slice = max(1, intdiv($this->seconds, self::SLICES));
    $endsAt = gmdate(Clock::STORED, $instant - $instant % $slice + $slice + $this->seconds);
    $this->db->prepare('delete from rate_limit where ends_at <= :now')
      ->execute(['now' => gmdate(Clock::STORED, $instant)]);
    $this->db->prepare('insert into rate_limit (id, bucket, ends_at, count)'
      . ' values (:id, :bucket, :ends_at, 1)'
      . ' on conflict (bucket, ends_at) do update set count = count + 1')
      ->execute(['id' => $this->uuid->generate(), 'bucket' => $bucket, 'ends_at' => $endsAt]);
    $counted = $this->db->prepare('select sum(count) from rate_limit where bucket = :bucket');
    $counted->execute(['bucket' => $bucket]);

    return (int) $counted->fetchColumn() <= $this->attempts;
  }
}
