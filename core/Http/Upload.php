<?php

declare(strict_types=1);

namespace Http;

/* The one file a request carried, as PHP left it in `upload_tmp_dir`: the name
   the browser sent is content and never a path, and `error` is PHP's own code,
   since a file stopped at `upload_max_filesize` arrives as that code and nothing
   else. Only `is_uploaded_file` makes the path trustworthy, and the one reader
   asks it. */
final readonly class Upload
{
  public function __construct(
    public string $name,
    public string $path,
    public int $size,
    public int $error,
  ) {}

  /** @param array<string, mixed> $files PHP's `$_FILES` */
  public static function from(array $files): ?self
  {
    $file = $files['file'] ?? null;
    if (!is_array($file) || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)) {
      return null;
    }

    return new self($file['name'], $file['tmp_name'], (int) ($file['size'] ?? 0),
      (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
  }

  /* The smaller of PHP's two caps, which the Webspace sets and nothing here can
     raise: a file past either dies at the server with no page to explain it. */
  public static function limit(): int
  {
    $caps = array_filter([ini_parse_quantity((string) ini_get('upload_max_filesize')),
      ini_parse_quantity((string) ini_get('post_max_size'))], fn (int $cap): bool => $cap > 0);

    return $caps === [] ? PHP_INT_MAX : min($caps);
  }
}
