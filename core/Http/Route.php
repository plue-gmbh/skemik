<?php

declare(strict_types=1);

namespace Http;

/* A match: which endpoint, and the path's named segments as they were sent. */
final readonly class Route
{
  /** @param array<string, string> $parameters */
  public function __construct(public Endpoint $endpoint, public array $parameters) {}

  public function parameter(string $name): string
  {
    return $this->parameters[$name] ?? throw new \LogicException(sprintf('no path segment %s', $name));
  }

  /* What a route may carry and need not: the `.fmt` spelling of an address. */
  public function optional(string $name): ?string
  {
    return $this->parameters[$name] ?? null;
  }
}
