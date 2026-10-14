<?php

declare(strict_types=1);

namespace Http;

/* Every URL the application answers, read from `ROUTES` and never discovered. A
   state change takes `{id}`, but for the feed's `PATCH`, which a machine aims at
   the `@id` it read and whose `If-Match` pins the record; a route asks for a
   capability, never a role. The locale is a path segment, verbatim `de-DE`,
   because it is half of the identifier: a `?locale=` would make an `@id`'s
   referent depend on an install's config. No version in the path, since a `/v2`
   would give every record a second address: the feed changes additively, and an
   incompatible change would be a profile in `Accept`. A bare `@id` answers 200
   and never redirects, because every one in a document gets dereferenced. */
final readonly class Router
{
  private const ROUTES = [
    'GET /' => Endpoint::Home,
    'GET /install' => Endpoint::Install,
    'POST /install' => Endpoint::InstallRun,
    'GET /login' => Endpoint::LoginForm,
    'POST /login' => Endpoint::LoginAsk,
    'POST /login/code' => Endpoint::LoginRedeem,
    'POST /logout' => Endpoint::Logout,
    'GET /c/{collection}' => Endpoint::Collection,
    'GET /search' => Endpoint::Search,
    'POST /preview' => Endpoint::Preview,
    'GET /changes' => Endpoint::Activity,
    'POST /changes/{id}/redact' => Endpoint::ActivityRedact,
    'GET /trash/{collection}' => Endpoint::Trash,
    'POST /c/{collection}' => Endpoint::RecordCreate,
    'GET /c/{collection}/{slug}' => Endpoint::Record,
    'PATCH /c/{collection}/{id}' => Endpoint::RecordPatch,
    'POST /c/{collection}/trash' => Endpoint::RecordsDelete,
    'POST /c/{collection}/restore' => Endpoint::RecordsRestore,
    'POST /c/{collection}/purge' => Endpoint::RecordsPurge,
    'POST /c/{collection}/{id}/delete' => Endpoint::RecordDelete,
    'POST /c/{collection}/{id}/restore' => Endpoint::RecordRestore,
    'POST /c/{collection}/{id}/purge' => Endpoint::RecordPurge,
    'POST /c/{collection}/{id}/translation' => Endpoint::RecordTranslation,
    'POST /c/{collection}/{id}/link' => Endpoint::RecordLink,
    'POST /c/{collection}/{id}/unlink' => Endpoint::RecordUnlink,
    'POST /c/{collection}/{id}/file' => Endpoint::MediaReplace,
    'POST /c/{collection}/fields' => Endpoint::FieldAdd,
    'PATCH /c/{collection}/fields/{key}' => Endpoint::FieldEdit,
    'POST /c/{collection}/fields/{key}/remove' => Endpoint::FieldRemove,
    'POST /media' => Endpoint::MediaUpload,
    'GET /media/{name}' => Endpoint::MediaFile,
    'GET /media/{name}/thumb' => Endpoint::MediaThumb,
    'POST /settings/accounts' => Endpoint::AccountCreate,
    'PATCH /settings/accounts/{id}' => Endpoint::AccountEdit,
    'POST /settings/accounts/{id}/remove' => Endpoint::AccountRemove,
    'POST /settings/tokens' => Endpoint::TokenIssue,
    'POST /settings/tokens/{id}/rules/{rule}/remove' => Endpoint::TokenRuleRemove,
    'POST /settings/tokens/{id}/revoke' => Endpoint::TokenRevoke,
    'POST /settings/locales' => Endpoint::LocaleAdd,
    'POST /settings/locales/order' => Endpoint::LocaleOrder,
    'POST /settings/locales/{tag}/remove' => Endpoint::LocaleRemove,
    'GET /settings/backups' => Endpoint::Backups,
    'POST /settings/backups' => Endpoint::BackupTake,
    'POST /settings/backups/{name}/restore' => Endpoint::BackupRestore,
    'POST /settings/backups/{name}/download' => Endpoint::BackupDownload,
    'GET /settings/update' => Endpoint::Update,
    'GET /settings/errors' => Endpoint::Errors,
    'GET /settings/checks' => Endpoint::Checks,
    'GET /export' => Endpoint::Export,
    'POST /export' => Endpoint::ExportTake,
    'GET /robots.txt' => Endpoint::Robots,
    'GET /.well-known/security.txt' => Endpoint::SecurityText,
    'GET /feed-panel/{locale}' => Endpoint::PanelLocale,
    'GET /feed-panel/{locale}/{collection}[.fmt]' => Endpoint::PanelCollection,
    'GET /feed-panel/{locale}/{collection}/{slug}[.fmt]' => Endpoint::PanelRecord,
    'GET /feed' => Endpoint::FeedCatalog,
    'GET /feed/{locale}' => Endpoint::FeedLocale,
    'GET /feed/{locale}/profile' => Endpoint::FeedProfile,
    'GET /feed/{locale}/{collection}[.fmt]' => Endpoint::FeedCollection,
    'GET /feed/{locale}/{collection}/{slug}[.fmt]' => Endpoint::FeedRecord,
    'POST /feed/{locale}/{collection}' => Endpoint::IngestCreate,
    'PATCH /feed/{locale}/{collection}' => Endpoint::IngestArrange,
    'PATCH /feed/{locale}/{collection}/{slug}' => Endpoint::IngestPatch,
    'OPTIONS /feed' => Endpoint::FeedPreflight,
    'OPTIONS /feed/{locale}' => Endpoint::FeedPreflight,
    'OPTIONS /feed/{locale}/{collection}[.fmt]' => Endpoint::FeedPreflight,
    'OPTIONS /feed/{locale}/{collection}/{slug}[.fmt]' => Endpoint::FeedPreflight,
  ];

  /* The four spellings, here because this file decides what a segment is: a
     suffix that is none of them stays part of the segment and answers as the
     collection or record nobody has. */
  private const FORMATS = ['jsonld', 'csv', 'md', 'html'];

  /* A segment is never empty and never holds a slash, so `/c/` matches nothing. */
  public function match(Request $request): Route
  {
    if ($request->oversized()) {
      throw new BodyTooLarge(sprintf('the body is past the %d bytes this host takes', Request::bodyLimit()));
    }
    $unreadable = $request->unreadable();
    if ($unreadable !== null) {
      throw UnreadableText::in($unreadable);
    }
    $segments = explode('/', trim($request->path, '/'));
    $asked = $request->method === 'HEAD' ? 'GET' : $request->method;
    foreach (self::ROUTES as $route => $endpoint) {
      [$method, $pattern] = explode(' ', $route);
      $parameters = $this->parameters(explode('/', trim($pattern, '/')), $segments);
      if ($method === $asked && $parameters !== null) {
        return new Route($endpoint, $parameters);
      }
    }

    throw RouteNotFound::for($request->method, $request->path);
  }

  /**
   * @param list<string> $pattern
   * @param list<string> $segments
   * @return ?array<string, string> null when the path is another route's
   */
  private function parameters(array $pattern, array $segments): ?array
  {
    if (count($pattern) !== count($segments)) {
      return null;
    }
    $parameters = [];
    foreach ($pattern as $index => $part) {
      $segment = $segments[$index];
      $name = [];
      if (preg_match('/^\{(\w+)\}(\[\.fmt\])?$/', $part, $name) === 1 && $segment !== '') {
        $suffixed = ($name[2] ?? '') !== '';
        [$segment, $format] = $suffixed ? $this->suffix($segment) : [$segment, null];
        $parameters[$name[1]] = $segment;
        $parameters = $format === null ? $parameters : [...$parameters, 'format' => $format];
      } elseif ($part !== $segment) {
        return null;
      }
    }

    return $parameters;
  }

  /** @return array{string, ?string} the segment as it addresses, and the format asked for */
  private function suffix(string $segment): array
  {
    $dot = strrpos($segment, '.');
    $format = $dot === false ? '' : substr($segment, $dot + 1);

    return in_array($format, self::FORMATS, true)
      ? [substr($segment, 0, (int) $dot), $format]
      : [$segment, null];
  }
}
