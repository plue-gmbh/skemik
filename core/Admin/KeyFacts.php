<?php

declare(strict_types=1);

namespace Admin;

use Auth\Token;
use Domain\EntityId;
use Domain\Exceptions\EntityNotFound;
use Storage\EntityRepository;
use Support\Clock;
use Support\Words;

/* A key's facts as words, the same on a badge, in the register and in a
   dialog: who issued it, when it runs out, when it was last used. Relative
   where the question is whether the integration is alive. */
final readonly class KeyFacts
{
  public function __construct(
    private EntityRepository $entities,
    private Clock $clock,
    private Words $words,
  ) {}

  public function issuer(Token $token): string
  {
    try {
      $person = $this->entities->find(new EntityId($token->createdBy));
    } catch (EntityNotFound) {
      return $this->words->get('sheet.someone');
    }

    return (string) ($person->props->string('name') ?? $this->words->get('sheet.someone'));
  }

  public function issued(Token $token): string
  {
    return $this->words->get('grant.issued', ['name' => $this->issuer($token),
      'date' => $this->date($token->createdAt)]);
  }

  public function expiry(Token $token): string
  {
    $left = (int) ceil(((new \DateTimeImmutable($token->expiresAt))->getTimestamp()
      - $this->clock->now()->getTimestamp()) / 86400);

    return $this->words->get('grant.expires', ['date' => $this->date($token->expiresAt), 'n' => (string) $left]);
  }

  public function used(Token $token): string
  {
    if ($token->lastUsedAt === null) {
      return $this->words->get('grant.unused');
    }
    $minutes = intdiv($this->clock->now()->getTimestamp()
      - (new \DateTimeImmutable($token->lastUsedAt))->getTimestamp(), 60);
    $when = match (true) {
      $minutes < 60 => $this->words->get('ago.hour'),
      $minutes < 1440 => $this->words->get('ago.hours', ['n' => (string) intdiv($minutes, 60)]),
      default => $this->words->get('ago.days', ['n' => (string) intdiv($minutes, 1440)]),
    };

    return $this->words->get('grant.used', ['when' => $when]);
  }

  /* What a newer key of the name means for this one. */
  public function replaced(Token $token, Token $newer): string
  {
    return $this->words->get('grant.replaced', ['date' => $this->date($newer->createdAt),
      'until' => $this->date($token->expiresAt)]);
  }

  public function date(string $stored): string
  {
    $local = new \DateTimeImmutable($this->clock->render($stored), new \DateTimeZone('UTC'));

    return (string) (new \IntlDateFormatter($this->words->language, \IntlDateFormatter::MEDIUM,
      \IntlDateFormatter::NONE, 'UTC'))->format($local);
  }
}
