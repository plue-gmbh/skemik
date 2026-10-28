<?php

declare(strict_types=1);

namespace Ingest;

use Auth\Authorizer;
use Auth\GrantCapability;
use Auth\Token;
use Domain\ActionType;
use Domain\Exceptions\EntityNotFound;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Domain\Props;
use Domain\Slug;
use Domain\Violation;
use Feed\FeedController;
use Http\Request;
use Http\Response;
use Http\Route;
use Http\TooManyRequests;
use Profile\Profile;
use Storage\Collection;
use Storage\CollectionField;
use Storage\CollectionStore;
use Storage\HouseItem;
use Storage\HouseObject;
use Storage\RateLimit;
use Storage\SettingStore;
use Support\Json;
use Support\JsonFailed;

/* A `PATCH` on the collection's own address: the arrangement a key holding
   `arrange` there writes, as the entry its catalogue shows it
   (`Feed\Catalog`), so what one house's catalogue shows is written to the next.
   The `variableMeasured` sent is the whole arrangement in its order: a field
   named is carried, one left out is taken off with its values kept, as the
   admin takes one off, and each leaves the house where `isAccessibleForFree`
   says so; what no house arranges by hand is refused (`Arrangement`). Letting
   a withheld field out weighs what setting a public price does, so a changed
   arrangement is logged under the key's name, naming the fields let out,
   withheld, added and removed. No `If-Match`: an arrangement is written whole,
   so there is no lost update to refuse, only the last script's order. */
final readonly class ArrangeController
{
  /** @param \Closure(Token): \Storage\ActivityLog $logFor the log, under the key */
  public function __construct(
    private Authorizer $authorizer,
    private FeedController $feed,
    private CollectionStore $collections,
    private SettingStore $settings,
    private Profile $profile,
    private Arrangement $arrangement,
    private RateLimit $perToken,
    private RateLimit $perClient,
    private \Closure $logFor,
    private Json $json = new Json(),
  ) {}

  public function arrange(Request $request, Route $route): Response
  {
    $collection = $this->admitted($request, $route);
    $token = $this->authorizer->requireRule($collection, GrantCapability::Arrange);
    $this->locale($route);
    [$fields, $violations] = $this->arrangement->fields($this->profile->byType($collection->type),
      $this->variables($request));
    if ($violations !== []) {
      return $this->refused($violations);
    }
    $arranged = $collection->carrying($fields);
    $this->collections->save($arranged);
    $this->logged($token, $collection, $arranged);

    return $this->feed->entry($request, $route, $arranged);
  }

  /* The buckets a record write counts in, so a key's minute is one minute. */
  private function admitted(Request $request, Route $route): Collection
  {
    $token = $this->perToken->hit('ingest:' . hash('sha256', (string) $request->bearer()));
    $client = $this->perClient->hit('ingest:client:' . hash('sha256', $request->client));
    if (!$token || !$client) {
      throw new TooManyRequests('too many writes from this key or this address; try again in a minute');
    }
    $this->authorizer->requireToken();
    $slug = $route->parameter('collection');
    try {
      $collection = $this->collections->bySlug(new Slug($slug));
    } catch (MalformedValue) {
      $collection = null;
    }

    return $collection ?? throw EntityNotFound::withId($slug);
  }

  /** @return list<mixed> the entries sent, one or many */
  private function variables(Request $request): array
  {
    try {
      $document = $this->json->decode($request->body);
    } catch (JsonFailed) {
      throw MalformedValue::notA('a JSON object', 'the body');
    }
    $sent = is_array($document) && !array_is_list($document) ? $document['variableMeasured'] ?? null : null;
    if ($sent === null) {
      throw MalformedValue::notA('an object naming its fields under variableMeasured', 'the body');
    }

    return is_array($sent) && array_is_list($sent) ? $sent : [$sent];
  }

  /* What changed, by field key, and nothing where the arrangement stands as it
     was: a field added public counts as let out too, since that is the disclosure. */
  private function logged(Token $token, Collection $before, Collection $after): void
  {
    $flags = fn (Collection $collection): array => array_column(array_map(fn (CollectionField $field): array
      => [$field->key, $field->feed], $collection->fields), 1, 0);
    [$was, $is] = [$flags($before), $flags($after)];
    if ($was === $is) {
      return;
    }
    $out = fn (array $flags): array => array_keys(array_filter($flags));
    $changed = ['let_out' => array_values(array_diff($out($is), $out($was))),
      'withheld' => array_values(array_intersect(array_diff($out($was), $out($is)), array_keys($is))),
      'added' => array_values(array_diff(array_keys($is), array_keys($was))),
      'removed' => array_values(array_diff(array_keys($was), array_keys($is)))];
    ($this->logFor)($token)->record(ActionType::Update, new HouseObject(HouseItem::Collection,
      $before->slug->value), new Props(array_filter($changed)));
  }

  /** @param list<Violation> $violations */
  private function refused(array $violations): Response
  {
    $said = [];
    foreach ($violations as $violation) {
      $said[$violation->field] ??= $violation->said();
    }
    $listed = array_map(fn (string $field, string $reason): array => ['field' => $field, 'reason' => $reason],
      array_keys($said), $said);

    return Response::json($this->json->encode(['error' => 'the arrangement does not fit the type',
      'violations' => $listed]), 422);
  }

  /* A locale the house does not hold is no address of this install. */
  private function locale(Route $route): Locale
  {
    $tag = $route->parameter('locale');

    return array_find($this->settings->locales(), fn (Locale $locale): bool => $locale->tag === $tag)
      ?? throw EntityNotFound::withId($tag);
  }
}
