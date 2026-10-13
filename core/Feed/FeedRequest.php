<?php

declare(strict_types=1);

namespace Feed;

use Domain\Exceptions\AskedTooMuch;
use Domain\Exceptions\MalformedValue;
use Domain\Locale;
use Http\Request;
use Profile\FieldDefinition;
use Profile\FieldKind;
use Profile\Profile;
use Profile\SchemaOrg;
use Storage\Collection;
use Storage\CollectionField;
use Storage\Query;
use Support\Clock;

/* The query string as a `Storage\Query`. One sort key, since a page orders the
   fifty it holds. A name given twice is refused, because PHP would keep one in
   silence, so `,` is the one spelling of *any of*. A year, month or day on an
   instant is read here as the house's, the one place the zone is known. A field
   withheld from this reader is refused in the words of one the type lacks, since
   a filter answering to it reads the value out a guess at a time. There is no
   status parameter: what may be read is `PublicFilter`'s. A list whose
   membership follows from a predicate is this query, and one somebody chose and
   ordered is an `ItemList` record. */
final readonly class FeedRequest
{
  private const PARAMETERS = ['sort', 'q', 'page', 'per_page', 'render'];

  private const PERIOD = '/^\d{4}(-\d{2}){0,2}$/';
  private const STEPS = [4 => '+1 year', 7 => '+1 month', 10 => '+1 day'];
  /* A text's every value is weighed against every record of the type, so a
     filter holding more would cost a stranger's one question more than a page of
     200 does; every other kind of filter is held to the same number. */
  private const VALUES = 50;

  public function __construct(private SchemaOrg $schema, private Profile $profile, private Clock $clock) {}

  /* No locale is the admin's reading of every locale; a feed route always names one.
     @param Collection $read the collection carrying only the fields this reader reads */
  public function query(Request $request, Collection $read, ?Locale $locale): Query
  {
    if ($request->repeated !== []) {
      throw MalformedValue::named('parameter given once, a comma between its values',
        $request->repeated[0]);
    }
    $filters = [];
    foreach (array_keys($request->query) as $name) {
      if (!in_array($name, self::PARAMETERS, true)) {
        $key = $this->key($read, $name);
        $filters[$key] = $this->values($this->profile->byType($read->type)->field($key), $name, $request);
      }
    }
    $sort = (string) $request->query('sort');

    return new Query($read->type, $filters, [], $locale,
      $sort === '' ? null : $this->key($read, ltrim($sort, '-')), str_starts_with($sort, '-'),
      (string) $request->query('q'), $this->number($request->query('page'), 1),
      $this->number($request->query('per_page'), Query::PER_PAGE),
      array_map(fn (CollectionField $field): string => $field->key, $read->fields));
  }

  /* The refusal names the parameter as it was written, not the key behind it. A
     name is asked of the whole type and only then of what this reader reads: a
     unit's `unitCode` names four fields of a product, and the one a reader
     happens to read must not make it mean something. */
  private function key(Collection $read, string $name): string
  {
    if ($name === 'slug') {
      return $name;
    }
    $meant = $this->meant($read->type, $name);
    if (count($meant) > 1) {
      throw MalformedValue::named('name of one field of ' . basename($read->type), $name);
    }
    $held = $meant === [] ? null : array_find($read->fields,
      fn (CollectionField $field): bool => $field->key === $meant[0]->key);

    return $held?->key ?? throw MalformedValue::named('field of ' . basename($read->type), $name);
  }

  /* The type's own field before a value object's key of the same name: `url` at
     the top of an event's document is the event's, not its virtual location's.
     @return list<FieldDefinition> */
  private function meant(string $type, string $name): array
  {
    $named = array_values(array_filter($this->profile->byType($type)->fields(),
      fn (FieldDefinition $field): bool => $this->schema->parameterName($field->key) === $name));
    $own = array_values(array_filter($named,
      fn (FieldDefinition $field): bool => $field->valueObject === null));

    return $own === [] ? $named : $own;
  }

  /* An empty value would match every row holding the field, a text's start
     being nothing, so `?name=` and `?name=a,` are refused. A link is read as it
     is stored (`JsonLd::held`), so the spelling a document shows finds its row.
     @return list<string> */
  private function values(?FieldDefinition $field, string $name, Request $request): array
  {
    $values = explode(',', (string) $request->query($name));
    if (in_array('', $values, true)) {
      throw MalformedValue::named('filter with a value on each side of a comma', $name);
    }
    if (count($values) > self::VALUES) {
      throw AskedTooMuch::values($name, self::VALUES);
    }
    $instants = $field?->kind === FieldKind::DateTime;

    return array_map(fn (string $one): string => $instants ? $this->instants($one)
      : (string) JsonLd::held($field, $one, $request->origin()), $values);
  }

  /* A period's first and last second in the house's zone, the last being the one
     before the next period starts, a wall time read in it, and anything else
     left for `Storage\Query` to refuse by name. */
  private function instants(string $value): string
  {
    [$from, $to] = str_contains($value, '..') ? explode('..', $value, 2) : [$value, $value];

    return $this->instant($from, false) . '..' . $this->instant($to, true);
  }

  private function instant(string $end, bool $last): string
  {
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $end) === 1) {
      return $this->clock->parse($end);
    }
    $start = substr($end . '-01-01', 0, 10);
    $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    if (preg_match(self::PERIOD, $end) !== 1 || $day === false || $day->format('Y-m-d') !== $start) {
      return $end;
    }
    if (!$last) {
      return $this->clock->dayStart($start);
    }
    $next = $this->clock->dayStart($day->modify(self::STEPS[strlen($end)])->format('Y-m-d'));

    return (new \DateTimeImmutable($next))->modify('-1 second')->format(Clock::STORED);
  }

  /* An unreadable number is a zero and `Storage\Query` refuses it by name, so
     `?page=abc` or `?page=2abc` is answered rather than silently read as a page. */
  private function number(?string $value, int $absent): int
  {
    return $value === null ? $absent : (ctype_digit($value) ? (int) $value : 0);
  }
}
