<?php

/* Skemik
   The database that ships its own data model
   https://skemik.org

   Copyright © 2026 plue GmbH
   SPDX-License-Identifier: BUSL-1.1
   https://plue.tech */

declare(strict_types=1);

/* The front controller, and the only file in the tree with side effects. Every
   other file defines a class or renders a template when included and does
   nothing when requested directly, which is what remains where a host ignores
   `.htaccess`. What stays here is what a request does to the world: the
   settings, the boot refusal, the session, every header and byte sent, and the
   work after the answer. Which objects answer a route is `core/Wiring`'s, and
   this file builds only what those need before a database is open. */

spl_autoload_register(function (string $class): void {
  $path = __DIR__ . '/core/' . str_replace('\\', '/', $class) . '.php';
  if (is_file($path)) {
    require $path;
  }
});

/* Settings no `php.ini` is trusted for: a warning or a trace's argument shows a stranger a path
   or a code, and a host's precision of 17 writes 19.9 as 19.899999999999999. */
header_remove('X-Powered-By');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1');
ini_set('serialize_precision', '-1');
ini_set('precision', '-1');
$request = Http\Request::from($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
if (PHP_SAPI === 'cli-server'
  && preg_match('#^/view/[\w/.-]+\.(css|js|woff2|ico|png|webmanifest)$#', $request->path) === 1) {
  return false;
}
$template = new Support\Template(__DIR__ . '/view');
$words = new Support\Words(__DIR__ . '/language', $request->header('Accept-Language') ?? '');

/* The boot refusal, free, so asked on every request. This file parses from PHP 8.2 up, so an older PHP gets
   this refusal and not a parse error; what needs 8.5 is only autoloaded. */
$missing = array_values(array_filter(['pdo_sqlite', 'intl', 'dom', 'mbstring'],
  fn (string $name): bool => !extension_loaded($name)));
$boot = match (true) {
  PHP_VERSION_ID < 80500 => $words->get('boot.php', ['needed' => '8.5', 'found' => PHP_VERSION]),
  $missing !== [] => $words->get('boot.extension', ['extension' => $missing[0]]),
  default => null,
};
$refuse = function (Support\Halt $halt) use ($template, $words): void {
  http_response_code(503);
  header('Content-Type: text/html; charset=utf-8');
  $halt->passes && header('Retry-After: 3');
  echo $template->render($halt->passes ? 'pages/waiting' : 'pages/failure',
    ['words' => $words, 'sentence' => $halt->sentence]);
};
if ($boot !== null) {
  $refuse(new Support\Halt($boot));
  return;
}
$utc = new Support\Clock(new DateTimeZone('UTC'));

/* Sending: a file is opened before the status line, so one a swap or a restore took away since it
   was measured answers 404, never a 200 shorter than the length it promised. */
$send = function (Http\Response $response) use ($request): void {
  $in = $response->file === null || !is_file($response->file->path) ? false : fopen($response->file->path, 'r');
  $response = $response->file !== null && $in === false ? $response->gone() : $response;
  http_response_code($response->status);
  foreach ($response->headersFor($request) as $name => $value) {
    header($name . ': ' . $value);
  }
  echo $response->body;
  $out = $in === false ? false : fopen('php://output', 'w');
  if ($in !== false && $out !== false) {
    stream_copy_to_stream($in, $out, $response->file->length, $response->file->offset);
  }
};
$firstRun = new Wiring\FirstRun(__DIR__, $request, $template, $words, $utc);

/* Before the first run only it answers; any other address a bare line, as an empty host's would. */
if (!is_file(__DIR__ . '/config.php')) {
  try {
    $response = $firstRun->answer((new Http\Router())->match($request));
  } catch (Http\RouteNotFound) {
    $response = new Http\Response(404, "Not Found\n", ['Content-Type' => 'text/plain; charset=utf-8']);
  } catch (Throwable $failure) {
    $response = (new Http\ErrorHandler(new Http\Logger('', $utc), $template, $words))
      ->handle($failure, $request, null);
  }
  $send($response);
  return;
}

/* `config.php`, read before a database opens: `/install` wrote every key, the relay's with no host
   and the contact `null`, so an empty one is none, and a folder that takes no write is refused
   here, not as a 500 naming nothing. */
$config = require __DIR__ . '/config.php';
$data = (string) $config['data'];
$address = rtrim(is_string($config['address'] ?? null) ? $config['address'] : '', '/');
if (preg_match('#^https?://[^/]+$#', $address) !== 1) {
  $refuse(new Support\Halt($words->get('boot.address')));
  return;
}
$relay = (string) ($config['relay']['host'] ?? '') !== '' ? new Auth\Smtp((string) $config['relay']['host'],
  (int) $config['relay']['port'], (string) $config['relay']['user'], (string) $config['relay']['password'],
  (string) $config['relay']['sender'], (string) parse_url($address, PHP_URL_HOST)) : null;
$logger = new Http\Logger($data . '/log', $utc);
$named = fn (string $key): ?string => (string) ($config[$key] ?? '') !== '' ? (string) $config[$key] : null;
$premises = new Admin\Premises($data, $address, (bool) ($config['daily_backups'] ?? true),
  (bool) ($config['updates'] ?? true), ($config['updates'] ?? true) === 'early',
  $named('security_contact'), $named('login_note'), $named('login_link'));
$halt = (new Admin\Probes($data,
  new Admin\DataLayout(__DIR__, new Admin\Proofs(new Support\Fetch(), $words), $words),
  new Support\Fetch(), $utc, $words, new Update\UpdateCheck($data, new Support\Fetch(), $utc)))
  ->halt($premises->address);
if ($halt !== null) {
  $refuse($halt);
  return;
}

/* The answer: the route first, since a first run resumed and the holdings' move each come before
   a session is resumed or a table read. */
[$common, $sessioned] = [null, false];
try {
  assert(($running = (new Support\Payload(__DIR__, $data))->running()) === [],
    implode(', ', $running) . ' runs something when included');
  assert(($apart = (new Support\Payload(__DIR__, $data))->found('public', Storage\Query::publicDisagreements(...)))
    === [], implode(', ', $apart) . ' is public to one of Storage\Query::PUBLIC and Status::isPublicAt only');
  $route = (new Http\Router())->match($request);
  if ($route->endpoint->isFirstRun()) {
    $send($firstRun->resumed($route, $premises));
    return;
  }
  $moving = (new Wiring\Arrival(__DIR__, $premises, $utc, $words))->move()->halt();
  if ($moving !== null) {
    $refuse($moving);
    return;
  }
  $session = new Auth\Session($data . '/sessions', $request->https, $utc);
  $sessioned = !$route->endpoint->isMachine();
  $sessioned && $session->resume($request->cookie('session'));
  $common = new Wiring\Common(__DIR__, $premises, $relay, $request, $session, $template, $utc);
  $response = (new Wiring\Dispatch($common))->answer($route);
} catch (Throwable $failure) {
  $response = (new Http\ErrorHandler($logger, $template, $common?->words ?? $words))
    ->handle($failure, $request, $common?->account?->id);
}

/* After the answer: a cookie that named no live session is cleared and none set unless the answer
   began one; the work that follows runs once the bytes have left, so nobody times it. */
if ($sessioned && ($session->active() || $request->cookie('session') !== null)) {
  $response = $response->with(['Set-Cookie' => $session->cookie()]);
  session_write_close();
}
$send($response);
if ($response->after !== null) {
  ob_get_level() > 0 && ob_end_flush();
  function_exists('fastcgi_finish_request') ? fastcgi_finish_request() : flush();
  try {
    ($response->after)();
  } catch (Throwable $failure) {
    (new Http\ErrorHandler($logger, $template, $common?->words ?? $words))
      ->handle($failure, $request, $common?->account?->id);
  }
}
