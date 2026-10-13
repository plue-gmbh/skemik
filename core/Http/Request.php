<?php

declare(strict_types=1);

namespace Http;

/* A readonly wrapper around the superglobals. They are read in `index.php` and
   nowhere else, and the body here, one byte past what the host takes at most;
   everything below gets this. Only strings survive: a query or a
   form value sent as an array (`?q[]=`) is dropped here rather than reaching a
   `(string)` cast that turns it into `Array`, and a query's is named in
   `$repeated` for the reader to refuse. */
final readonly class Request
{
  /**
   * @param array<string, string> $query
   * @param array<string, string> $form
   * @param array<string, string> $headers lowercased names
   * @param array<string, string> $cookies
   * @param list<string> $repeated query names sent more than once or as a list
   */
  public function __construct(
    public string $method,
    public string $path,
    public array $query = [],
    public array $form = [],
    public array $headers = [],
    public array $cookies = [],
    public string $body = '',
    public bool $https = false,
    public string $client = '',
    public ?Upload $upload = null,
    public array $repeated = [],
  ) {}

  /**
   * @param array<string, mixed> $server
   * @param array<string, mixed> $get
   * @param array<string, mixed> $post
   * @param array<string, mixed> $cookies
   * @param array<string, mixed> $files
   */
  public static function from(array $server, array $get, array $post, array $cookies, array $files = []): self
  {
    $body = (string) file_get_contents('php://input', false, null, 0, self::bodyLimit() + 1);
    $headers = self::headers($server);
    /* Cut at the query rather than parsed as a URL, which fails on a segment
       like `events:80` and would read the path as the home screen's. Decoded per
       segment, a slash one decodes to kept as `%2F`, so no encoded path is
       routed as two segments it was not. */
    $path = implode('/', array_map(fn (string $segment): string => str_replace('/', '%2F', rawurldecode($segment)),
      explode('/', explode('?', (string) ($server['REQUEST_URI'] ?? '/'), 2)[0])));
    $https = ($server['HTTPS'] ?? 'off') !== 'off' && ($server['HTTPS'] ?? '') !== '';

    return new self(strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')), $path,
      self::strings($get), self::strings($post), $headers, self::strings($cookies), $body, $https,
      self::client((string) ($server['REMOTE_ADDR'] ?? '')), Upload::from($files),
      self::repeated((string) ($server['QUERY_STRING'] ?? ''), $get));
  }

  /**
   * @param array<string, mixed> $server
   * @return array<string, string> by lower-case name
   */
  private static function headers(array $server): array
  {
    $headers = [];
    foreach ($server as $name => $value) {
      if (is_string($value) && str_starts_with((string) $name, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr((string) $name, 5)))] = $value;
      }
    }
    $headers['content-type'] = (string) ($server['CONTENT_TYPE'] ?? '');
    $headers['content-length'] = (string) ($server['CONTENT_LENGTH'] ?? '');
    /* Behind FastCGI the header only arrives through `.htaccess`'s rewrite,
       which renames what it sets once it has redirected to `index.php`. */
    $forwarded = $server['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if (!isset($headers['authorization']) && is_string($forwarded) && $forwarded !== '') {
      $headers['authorization'] = $forwarded;
    }

    return $headers;
  }

  /* The most a body may hold, the host's `post_max_size`: PHP holds a form to it
     and reads any other body whole, so one byte past it is read, and a body that
     reaches it, or declares it would, is refused before a route is asked. A file
     past it never arrives at all, PHP dropping every field beside it too. */
  public static function bodyLimit(): int
  {
    $cap = ini_parse_quantity((string) ini_get('post_max_size'));

    return $cap > 0 ? $cap : PHP_INT_MAX - 1;
  }

  public function oversized(): bool
  {
    return max(strlen($this->body), (int) $this->header('Content-Length')) > self::bodyLimit();
  }

  /* `HEAD` is a `GET` whose body the server drops, so it reads as one. */
  public function reads(): bool
  {
    return $this->method === 'GET' || $this->method === 'HEAD';
  }

  /* Who counts as one client in a rate limit, and where HTTPS is seen: the
     connection's own, since `X-Forwarded-*` are headers any client can send.
     Behind a proxy that speaks TLS for the host every visitor is one address and
     every `@id` reads `http://`; a `config.php` key naming the proxy comes when a
     house behind one asks. An
     IPv6 connection is usually handed a whole `/64`, so an address of its own
     per request would be a fresh allowance per request; an IPv4 address written
     as IPv6 stays whole. */
  private static function client(string $address): string
  {
    $packed = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false ? false : inet_pton($address);
    if ($packed === false || str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
      return $address;
    }

    return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
  }

  public function header(string $name): ?string
  {
    return $this->headers[strtolower($name)] ?? null;
  }

  public function query(string $name): ?string
  {
    return $this->query[$name] ?? null;
  }

  /* The admin's own parameters taken off before the feed's parser reads the rest. */
  public function without(string ...$names): self
  {
    return new self($this->method, $this->path, array_diff_key($this->query, array_flip($names)),
      $this->form, $this->headers, $this->cookies, $this->body, $this->https, $this->client, $this->upload,
      array_values(array_diff($this->repeated, $names)));
  }

  /* The address with one parameter set, as a view that fixes its order reads it. */
  public function with(string $name, string $value): self
  {
    return new self($this->method, $this->path, [...$this->query, $name => $value], $this->form,
      $this->headers, $this->cookies, $this->body, $this->https, $this->client, $this->upload, $this->repeated);
  }

  /* The part holding bytes that are not UTF-8, or null. Past the door such text
     fails the save that encodes it and prints as nothing where it is shown, so
     it is refused here. Only what can be stored or shown is asked: a file's
     bytes are not text and its name is. */
  public function unreadable(): ?string
  {
    $parts = ['the address' => [$this->path, ...array_keys($this->query), ...$this->query],
      'the form' => [...array_keys($this->form), ...$this->form], 'the body' => [$this->body],
      'the file name' => [$this->upload->name ?? '']];
    foreach ($parts as $part => $texts) {
      if (!mb_check_encoding(implode("\n", $texts), 'UTF-8')) {
        return $part;
      }
    }

    return null;
  }

  public function form(string $name): ?string
  {
    return $this->form[$name] ?? null;
  }

  public function cookie(string $name): ?string
  {
    return $this->cookies[$name] ?? null;
  }

  /* The origin an `@id` is written under: the name the request came under, so
     a house answering under two reads right under each. It is checked rather
     than trusted, since anything but a host and a port would be a header the
     caller chose, and it lands in every `@id` of the document. What the install
     says outside a request uses the address `config.php` holds (`Admin\Premises`). */
  public function origin(): string
  {
    $host = (string) $this->header('Host');

    return ($this->https ? 'https://' : 'http://')
      . (preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host) === 1 ? $host : 'localhost');
  }

  /* HTTP names the scheme without regard to case, and a key sent as `bearer` read
     as none would be served a stranger's reading without being told why. */
  public function bearer(): ?string
  {
    $sent = [];

    return preg_match('/^Bearer (\S+)$/i', (string) $this->header('Authorization'), $sent) === 1
      ? $sent[1]
      : null;
  }

  public function wantsJson(): bool
  {
    return str_contains((string) $this->header('accept'), 'application/json');
  }

  /* PHP keeps the last of `?a=1&a=2` and says nothing, and reads `?a[]=1` as a
     list that `strings()` drops, so both are named here for a reader to refuse
     rather than to read one value or none.
     @param array<string, mixed> $get
     @return list<string> */
  private static function repeated(string $query, array $get): array
  {
    $names = array_map(fn (string $pair): string => urldecode(explode('=', $pair, 2)[0]),
      explode('&', $query));
    $counted = array_count_values(array_filter($names, fn (string $name): bool => $name !== ''));
    $twice = array_keys(array_filter($counted, fn (int $count): bool => $count > 1));

    $listed = array_keys(array_filter($get, is_array(...)));

    return array_values(array_unique(array_map(strval(...), [...$twice, ...$listed])));
  }

  /**
   * @param array<string, mixed> $values
   * @return array<string, string>
   */
  private static function strings(array $values): array
  {
    return array_filter($values, is_string(...));
  }
}
