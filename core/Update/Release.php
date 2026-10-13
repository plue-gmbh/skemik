<?php

declare(strict_types=1);

namespace Update;

/* The two facts an install trusts: where the manifest naming the newest release
   is read, and the key every download is verified against, as minisign writes
   its public key file. Both live in the source and nowhere else, so nothing a
   hoster or a config key can switch stands in for them. A new key arrives in a
   release signed with the old one, so an install follows a rotation by updating,
   and a lost key means one release copied by hand. */
final readonly class Release
{
  public const ADDRESS = 'https://releases.skemik.org/manifest.json';
  public const KEY = "untrusted comment: minisign public key EE249A3476233EDC\n"
    . "RWTcPiN2NJok7rtTpL5Ipn/LHlDpGfDQGT2ZHkbGp1Lry05p4J71nMzW\n";
}
