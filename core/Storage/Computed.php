<?php

declare(strict_types=1);

namespace Storage;

use Domain\Status;
use Profile\Derivation;
use Profile\Derived;
use Profile\FieldDefinition;
use Profile\SchemaOrg;
use Profile\WrittenBy;
use Support\Json;

/* A derived field as the SQL its `Profile\Derivation` names, so a filter and a
   sort read the number the document states (`Feed\Derivations`), where `props`
   holds none. The function and the edge's direction shape the statement, and
   the predicate, the keys and the reading are bound. A gate the declaration
   names reads every row in force; without one a row counts where the reader
   reads it, as a search reaches an edge (`Search`), or every row in force where
   no reading is bound, which asks whether a value stands at all (`Counts`). A
   price range is no number, so it filters and sorts as the edge it reads. */
final readonly class Computed
{
  /* The spine's locale is a column, and a rating computes only where its reviews
     are rows here, so both compare what is stored. */
  public static function holds(FieldDefinition $field): bool
  {
    return $field->writtenBy === WrittenBy::Derived && $field->derivation?->function->readsEdge() === true;
  }

  /** The edge's two ends as the field reads it, the record's own first.
      @return array{string, string} */
  public static function ends(FieldDefinition $field): array
  {
    return self::holds($field) && $field->derivation?->inbound === true ? ['r.object_id', 'r.subject_id']
      : ['r.subject_id', 'r.object_id'];
  }

  public static function predicate(FieldDefinition $field, SchemaOrg $schema): string
  {
    return (string) $schema->propertyName(self::holds($field) ? $field->derivation?->path[0] ?? '' : $field->key);
  }

  /* Row `e`'s value, `$spec` the placeholder `spec()` is bound to. A target's key
     counts only where it holds a number, as `Feed\Derivations` reads it. */
  public static function value(FieldDefinition $field, string $spec): string
  {
    $how = $field->derivation ?? Derivation::spine();
    [$near, $far] = self::ends($field);
    $number = "case when json_type(t.props, json_extract($spec, '$.reads')) in ('integer', 'real')"
      . " then json_extract(t.props, json_extract($spec, '$.reads')) end";
    $taken = match ($how->function) {
      Derived::Count => $how->reads() === null ? 'count(*)' : "count($number)",
      Derived::Sum => "sum($number)",
      Derived::Min => "min($number)",
      Derived::Max => "max($number)",
      Derived::Avg => "round(avg($number), 2)",
      Derived::Locale, Derived::Range => 'null',
    };
    $value = "(select $taken from relation r cross join entity t on t.id = $far where $near = e.id"
      . " and r.predicate = json_extract($spec, '$.predicate') and t.deleted_at is null and "
      . ($how->from === null ? '' : "t.type = json_extract($spec, '$.from') and ") . self::gate($how, $spec) . ')';

    return $how->minus === null ? $value : "(case when json_type(e.props, json_extract($spec, '$.minus'))"
      . " = 'integer' then json_extract(e.props, json_extract($spec, '$.minus')) - $value end)";
  }

  /* Row `e` states a value, so a view shows the field; a price range does where a record outside the trash
     points at it, since its value is no number. */
  public static function stated(FieldDefinition $field, string $spec): string
  {
    [$near, $far] = self::ends($field);

    return $field->derivation?->function !== Derived::Range ? self::value($field, $spec) . ' is not null'
      : "exists (select 1 from relation r cross join entity t on t.id = $far where $near = e.id"
        . " and r.predicate = json_extract($spec, '$.predicate') and t.deleted_at is null)";
  }

  /* The target is read again as `e`, the row `Query::STATUS` names. */
  private static function gate(Derivation $how, string $spec): string
  {
    if ($how->gateField !== null) {
      return "coalesce(json_extract(t.props, json_extract($spec, '$.gate')) = json_extract($spec, '$.admitted'),"
        . " 0) = json_extract($spec, '$.admits')";
    }

    return "(json_type($spec, '$.readings') = 'null' or exists (select 1 from entity e"
      . " cross join json_each($spec, '$.readings') g where e.id = t.id"
      . " and e.type = json_extract(g.value, '$.type') and (json_array_length(g.value, '$.statuses') = 0 or "
      . Query::STATUS . " in (select value from json_each(g.value, '$.statuses'))) and"
      . " (json_array_length(g.value, '$.ids') = 0 or e.id in (select value from json_each(g.value, '$.ids')))))";
  }

  /** @param ?array<string, Query> $readings by type, what the reader reads of each; null for every row in force */
  public static function spec(FieldDefinition $field, SchemaOrg $schema, ?array $readings): string
  {
    $how = $field->derivation;
    $path = fn (?string $key): ?string => $key === null ? null : '$.' . $key;
    $gated = $how?->gateField !== null;

    return (new Json())->encode(['predicate' => self::predicate($field, $schema), 'reads' => $path($how?->reads()),
      'minus' => $path($how?->minus), 'gate' => $path($how?->gateField), 'admitted' => $how?->gateValue,
      'admits' => $how?->gateAdmits, 'from' => $how?->from === null ? null : 'https://schema.org/' . $how->from,
      'readings' => $gated || $readings === null ? null : array_values(array_map(self::reading(...), $readings))]);
  }

  /** @return array{type: string, statuses: list<string>, ids: list<string>} */
  private static function reading(Query $reading): array
  {
    return ['type' => $reading->type,
      'statuses' => array_map(fn (Status $status): string => $status->value, $reading->statuses),
      'ids' => $reading->filters['id'] ?? []];
  }
}
