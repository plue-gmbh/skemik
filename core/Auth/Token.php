<?php

declare(strict_types=1);

namespace Auth;

use Domain\Exceptions\MalformedValue;

/* A bearer token, which after the answer that showed it exists only as a SHA-256
   hash. It is an actor in its own name, its writes logged under its label, which
   is why the label is required: *Till* answers who changed an invoice, a blank
   answers nothing. It names its issuer with no foreign key, so a colleague who
   leaves takes neither the house's integrations nor the record of who set them
   up. *Never* is not representable, since a date decades ahead would say it
   anyway, so a key lives two years at most and is renewed on purpose. Both are
   asked where a key is issued and never of a stored row, so a row reading back
   wrong stops its own key and not the issuing of every other. */
final readonly class Token
{
  /* Two years, and the day they end on whatever hour the key was issued at. */
  private const LONGEST = 'P2Y1D';

  public function __construct(
    public string $id,
    public string $label,
    public string $createdAt,
    public string $createdBy,
    public string $expiresAt,
    public ?string $lastUsedAt = null,
    public ?string $revokedAt = null,
  ) {}

  public function issuable(): self
  {
    if (trim($this->label) === '') {
      throw MalformedValue::named('token name', $this->label);
    }
    if ($this->expiresAt <= $this->createdAt) {
      throw MalformedValue::named('expiry after issue', $this->expiresAt);
    }
    $latest = (new \DateTimeImmutable($this->createdAt))->add(new \DateInterval(self::LONGEST));
    if (new \DateTimeImmutable($this->expiresAt) > $latest) {
      throw MalformedValue::named('expiry within two years of issue', $this->expiresAt);
    }

    return $this;
  }
}
