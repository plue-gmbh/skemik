<?php

declare(strict_types=1);

namespace Support;

final class ZipFailed extends \RuntimeException
{
  public static function writing(string $path): self
  {
    return new self(sprintf('%s could not be written', $path));
  }

  public static function reading(string $path): self
  {
    return new self(sprintf('%s could not be read', $path));
  }
}
