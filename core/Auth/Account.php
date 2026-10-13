<?php

declare(strict_types=1);

namespace Auth;

use Domain\EntityId;
use Domain\Exceptions\MalformedValue;

/* What this install knows about its relationship to a `Person`: the login
   address, the role, the scopes. The name is the person's, so this has none, and
   no credential exists to hold. `write ⊆ read` is refused rather than repaired,
   because an account that may write what it cannot list is a bug wearing a
   configuration's clothes. A person holds one account at most. The address is
   lowercased because it is half of a unique key and the only thing typed at the
   login form: `Anna@` and `anna@` as two rows would be one person who can log in
   under only one of them. Its domain is kept as ASCII for the same reason, and
   because an address at `bäckerei-müller.de` is otherwise refused as none. */
final readonly class Account
{
  public string $email;

  public function __construct(
    public string $id,
    public EntityId $person,
    string $email,
    public Role $role,
    public TypeScope $write,
    public TypeScope $read,
    public string $createdAt,
    public ?string $lastLoginAt = null,
    public ?string $disabledAt = null,
  ) {
    $this->email = self::address($email);
    if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
      throw MalformedValue::named('login address', $email);
    }
    if (!$read->covers($write)) {
      $written = $write->stored() ?? 'every type';
      throw MalformedValue::named('write scope within the read scope', $written);
    }
  }

  /* The one spelling of a login address, wherever it is typed or looked up. */
  public static function address(string $typed): string
  {
    $address = mb_strtolower(trim($typed));
    $at = strrpos($address, '@');
    $host = $at === false ? '' : substr($address, $at + 1);
    $domain = $host === '' ? false : idn_to_ascii($host);

    return $domain === false ? $address : substr($address, 0, $at + 1) . $domain;
  }

  public function can(Capability $capability): bool
  {
    return $this->disabledAt === null && $this->role->can($capability);
  }
}
