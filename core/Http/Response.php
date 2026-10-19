<?php

declare(strict_types=1);

namespace Http;

/* A readonly description of the output, and of work that follows it. Only
   `index.php` sends it, so nothing else in the tree writes a header or a byte.
   A file is named rather than read, so a video is streamed from disk and never
   held in memory whole. */
final readonly class Response
{
  /** @param array<string, string> $headers */
  public function __construct(
    public int $status,
    public string $body = '',
    public array $headers = [],
    public ?FileBody $file = null,
    public ?\Closure $after = null,
  ) {}

  /* Work that runs once the answer has left, for what must not be timed from
     outside: `index.php` sends first and runs it after. */
  public function after(\Closure $work): self
  {
    return new self($this->status, $this->body, $this->headers, $this->file, $work);
  }

  /* A file whole, or the one range a player seeking in a video asks for.
     @param array<string, string> $headers */
  /** @psalm-taint-sink file $path read as it stands */
  public static function ranged(string $path, ?string $range, array $headers): self
  {
    $size = (int) filesize($path);
    $headers = [...$headers, 'Accept-Ranges' => 'bytes'];
    $asked = [];
    if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) $range, $asked) !== 1 || $asked[1] . $asked[2] === '') {
      return self::file(new FileBody($path, 0, $size), $headers, 200);
    }
    $start = $asked[1] === '' ? max(0, $size - (int) $asked[2]) : (int) $asked[1];
    $end = $asked[1] === '' || $asked[2] === '' ? $size - 1 : min((int) $asked[2], $size - 1);

    return $start > $end ? new self(416, '', ['Content-Range' => 'bytes */' . $size])
      : self::file(new FileBody($path, $start, $end - $start + 1),
        [...$headers, 'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end, $size)], 206);
  }

  /* Nothing sent, the caller holding what the tag names; the headers go with it,
     so a cache's lifetime starts again.
     @param array<string, string> $headers */
  public static function unchanged(array $headers): self
  {
    return new self(304, '', $headers);
  }

  /** @param array<string, string> $headers */
  private static function file(FileBody $file, array $headers, int $status): self
  {
    return new self($status, '', [...$headers, 'Content-Length' => (string) $file->length], $file);
  }

  public static function html(string $body, int $status = 200): self
  {
    return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
  }

  public static function json(string $body, int $status = 200): self
  {
    return new self($status, $body, ['Content-Type' => 'application/json; charset=utf-8']);
  }

  /* `303`, so a POST answered by a redirect is followed by a GET. */
  /** @psalm-taint-sink header $location sent as it stands */
  public static function redirect(string $location): self
  {
    return new self(303, '', ['Location' => $location]);
  }

  /* An answer's own headers override the admin's defaults, and work following it
     sends a length, so the client need not wait for that work; a file's is set already.
     @return array<string, string> */
  public function headersFor(Request $request): array
  {
    return [...(new SecurityHeaders())->for($request), ...(new CacheHeaders())->admin(), ...$this->headers,
      ...($this->after === null || $this->file !== null ? [] : ['Content-Length' => (string) strlen($this->body)])];
  }

  /* What a file answer becomes when its file is gone since it was measured: a
     plain `404` that keeps the session's cookie, the one header not about the file. */
  public function gone(): self
  {
    return new self(404, "Not Found\n", ['Content-Type' => 'text/plain; charset=utf-8',
      ...array_intersect_key($this->headers, ['Set-Cookie' => true])], null, $this->after);
  }

  /** @param array<string, string> $headers
      @psalm-taint-sink header $headers sent as they stand */
  public function with(array $headers): self
  {
    return new self($this->status, $this->body, [...$this->headers, ...$headers], $this->file, $this->after);
  }
}
