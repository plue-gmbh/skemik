<?php

declare(strict_types=1);

namespace Support;

/* One HTTP GET, for the proofs that a file is not served (`Admin\DataLayout`):
   what the install asks of its own address is what a stranger would get, so no
   redirect is followed, one saying nothing about the file, and the certificate
   is checked as a stranger's browser checks it. Over a socket rather than
   `file_get_contents`, which `allow_url_fopen` switches off on the hosts that
   most need the proof. HTTP/1.0, so no answer arrives chunked and a sentinel is
   never split across two chunks. A fetch of the server serving it needs a
   second worker: under `php -S` that is `PHP_CLI_SERVER_WORKERS`. The update's
   archive is saved to a file as it arrives rather than held, since one of
   many megabytes would not fit beside a request, and one shorter than its
   `Content-Length` is refused here, so a connection that broke off is the
   network's to try again and not a signature the release got wrong. */
final readonly class Fetch
{
  private const TIMEOUT_SECONDS = 5;
  private const LIMIT_BYTES = 1_000_000;

  public function get(string $url): Fetched
  {
    $socket = $this->asked($url);
    $answer = (string) stream_get_contents($socket, self::LIMIT_BYTES);
    fclose($socket);

    return $this->read($url, $answer);
  }

  /* A `200`'s body straight into a file, up to the limit: one past it is cut off
     and refused, as one shorter than its head said. */
  public function save(string $url, string $path, int $limit): void
  {
    $socket = $this->asked($url);
    $head = '';
    while (!str_ends_with($head, "\r\n\r\n") && strlen($head) < 65536 && ($line = fgets($socket)) !== false) {
      $head .= $line;
    }
    $status = $this->read($url, $head)->status;
    if ($status !== 200) {
      fclose($socket);
      throw FetchFailed::of($url, 'answered ' . $status);
    }
    $file = fopen($path, 'w') ?: throw FetchFailed::of($url, 'could not be saved to ' . $path);
    $copied = (int) stream_copy_to_stream($socket, $file, $limit + 1);
    fclose($file);
    fclose($socket);
    $incomplete = $this->incomplete($head, $copied, $limit);
    if ($incomplete !== null) {
      unlink($path);
      throw FetchFailed::of($url, $incomplete);
    }
  }

  /* Why a body of this length is not the whole file, or null where it is. */
  private function incomplete(string $head, int $copied, int $limit): ?string
  {
    $said = preg_match('/^Content-Length:\s*(\d+)\s*$/mi', $head, $length) === 1 ? (int) $length[1] : null;

    return match (true) {
      $copied > $limit => 'is past the ' . $limit . ' bytes a download may take',
      $said !== null && $copied !== $said => 'broke off after ' . $copied . ' of ' . $said . ' bytes',
      default => null,
    };
  }

  /** @return resource the socket, the request sent and the answer still to read */
  private function asked(string $url)
  {
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
      throw FetchFailed::of($url, 'not an address');
    }
    $https = $parts['scheme'] === 'https';
    $port = $parts['port'] ?? ($https ? 443 : 80);
    $socket = $this->open(sprintf('%s://%s:%d', $https ? 'tls' : 'tcp', $parts['host'], $port), $parts['host']);
    fwrite($socket, sprintf("GET %s HTTP/1.0\r\nHost: %s\r\nUser-Agent: Skemik\r\nConnection: close\r\n\r\n",
      ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''),
      isset($parts['port']) ? $parts['host'] . ':' . $port : $parts['host']));

    return $socket;
  }

  /** @return resource */
  private function open(string $target, string $host)
  {
    $socket = stream_socket_client($target, $number, $reason, self::TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT,
      stream_context_create(['ssl' => ['peer_name' => $host]]));
    if ($socket === false) {
      throw FetchFailed::of($target, $reason);
    }
    stream_set_timeout($socket, self::TIMEOUT_SECONDS);

    return $socket;
  }

  private function read(string $url, string $answer): Fetched
  {
    if (preg_match('#^HTTP/\d(?:\.\d)? (\d{3})#', $answer, $status) !== 1) {
      throw FetchFailed::of($url, 'no HTTP answer within ' . self::TIMEOUT_SECONDS . ' seconds');
    }
    $head = strpos($answer, "\r\n\r\n");

    return new Fetched((int) $status[1], $head === false ? '' : substr($answer, $head + 4));
  }
}
