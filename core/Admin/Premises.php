<?php

declare(strict_types=1);

namespace Admin;

/* What `config.php` connects the code to: the data folder, the address
   `/install` ran and proved its folders under, which the install uses wherever
   it speaks outside a request, a login mail and a fetch of itself, since a
   request's `Host` is whatever its sender typed, whether a snapshot of the
   holdings is taken daily, which a host that backs up properly switches off
   there, whether a release is installed by itself, which a copy ahead of the
   release declines, whether it is taken before the day a staged one is held
   back to, and who a security report about this site goes to first, the house
   or its agency, since all of them are facts about the machine. */
final readonly class Premises
{
  public function __construct(
    public string $data,
    public string $address,
    public bool $dailyBackups = true,
    public bool $updates = true,
    public bool $early = false,
    public ?string $securityContact = null,
  ) {}
}
