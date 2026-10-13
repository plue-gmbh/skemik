<?php

declare(strict_types=1);

namespace Update;

use Support\Clock;

/* What the owner's update screen says, kept as `update.json` in the data folder
   and outside `holdings/`, since what the machine did is no fact of the house a
   restore should bring back: when the install last checked and whether it
   could, the newest release the manifest named above the running one, the last
   release installed by itself and whether it was a security release, and the
   release that cannot be installed with the reason, and whether the cause was
   the machine's, tried again at the next check, or the release's, which stops
   the same release being tried again every hour. Times are stored instants, and
   one that is not, as after a hand edit, is missing: `checked` is read before
   every answer, and a time that throws there would fail every page. */
final readonly class UpdateReport
{
  public function __construct(
    public ?string $checked = null,
    public ?string $unreachable = null,
    public ?string $offered = null,
    public ?string $installed = null,
    public ?string $installedAt = null,
    public bool $security = false,
    public ?string $refused = null,
    public ?string $reason = null,
    public bool $again = false,
  ) {}

  public function checkedAt(string $at, ?string $unreachable, ?string $offered): self
  {
    return new self($at, $unreachable, $offered, $this->installed, $this->installedAt, $this->security,
      $this->refused, $this->reason, $this->again);
  }

  public function installing(string $version, string $at, bool $security): self
  {
    return new self($this->checked, $this->unreachable, $this->offered, $version, $at, $security);
  }

  public function refusing(string $version, string $reason, bool $again): self
  {
    return new self($this->checked, $this->unreachable, $this->offered, $this->installed, $this->installedAt,
      $this->security, $version, $reason, $again);
  }

  /* A release at or below the running one was copied by hand: it offers and blocks
     nothing. One installed above it was put back by a process the host stopped. */
  public function against(string $running): self
  {
    $above = fn (?string $version): ?string
      => $version !== null && version_compare($version, $running, '>') ? $version : null;
    $refused = $above($this->refused);
    $installed = $above($this->installed) === null ? $this->installed : null;

    return new self($this->checked, $this->unreachable, $above($this->offered), $installed,
      $installed === null ? null : $this->installedAt, $installed !== null && $this->security, $refused,
      $refused === null ? null : $this->reason, $refused !== null && $this->again);
  }

  /** @param array<string, mixed> $read */
  public static function from(array $read): self
  {
    $text = fn (string $key): ?string => isset($read[$key]) ? (string) $read[$key] : null;
    $instant = fn (string $key): ?string => $text($key) !== null
      && \DateTimeImmutable::createFromFormat(Clock::STORED, $text($key)) !== false ? $text($key) : null;

    return new self($instant('checked'), $text('unreachable'), $text('offered'), $text('installed'),
      $instant('installed_at'), (bool) ($read['security'] ?? false), $text('refused'), $text('reason'),
      (bool) ($read['again'] ?? false));
  }

  /** @return array<string, string|bool|null> */
  public function toArray(): array
  {
    return ['checked' => $this->checked, 'unreachable' => $this->unreachable, 'offered' => $this->offered,
      'installed' => $this->installed, 'installed_at' => $this->installedAt, 'security' => $this->security,
      'refused' => $this->refused, 'reason' => $this->reason, 'again' => $this->again];
  }
}
