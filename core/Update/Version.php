<?php

declare(strict_types=1);

namespace Update;

/* The application version, read from a constant in the source, never from the
   database — the source is the thing being replaced. It is also the version of
   the type definitions: they ship with the code, so no type carries a
   number of its own. `-dev` between releases, on the next patch: the lowest
   the next release can be, which names itself a patch or a minor. The schema
   version is no constant beside it: it is the number of the last script in
   `migrations/`, stamped into the database it moved, so a release without a
   script leaves it. The data folder is stamped with both once its holdings are
   moved to them (`Move`), so a copied release knows its first request.
   `RELEASED` is the day of the last release, typed once: `security.txt` counts
   its `Expires` from it. */
final readonly class Version
{
  public const APP = '1.0.5';
  public const RELEASED = '2026-10-21';
}
