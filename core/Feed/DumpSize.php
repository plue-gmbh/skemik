<?php

declare(strict_types=1);

namespace Feed;

/* What a dump will hold, measured before it is written: the records, each in
   four formats, and the files under `media/`, once each, with their bytes. */
final readonly class DumpSize
{
  public function __construct(public int $records, public int $files, public int $bytes) {}
}
