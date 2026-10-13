<?php

declare(strict_types=1);

namespace Auth;

/* Somebody proved who they are and may not do this. It names what was missing,
   because the caller is known and a refusal that hides its reason only sends them
   to guess — the point, not the exception. */
final class NotPermitted extends \RuntimeException
{
  public static function capability(Capability $capability): self
  {
    return new self(sprintf('this account may not %s', $capability->value));
  }

  public static function outsideWriteScope(string $type): self
  {
    return new self(sprintf('this account may not write %s', $type));
  }

  /* In the verbs the admin issues the key with, so the refusal and the key's screen say one thing. */
  public static function grant(string $collection, ?string $field, GrantCapability $capability): self
  {
    $what = $field === null ? $collection : $collection . '.' . $field;
    $verb = match ($capability) {
      GrantCapability::Create => 'create records in',
      GrantCapability::Set => 'modify',
      GrantCapability::Read => 'read',
      GrantCapability::Arrange => 'configure',
    };

    return new self(sprintf('this key may not %s %s', $verb, $what));
  }
}
