<?php

declare(strict_types=1);

namespace Admin;

use Domain\Reason;
use Domain\Violation;
use Support\Words;

/* A refusal in the colleague's language: a record check's reason worded under
   `violation.`, from the values its words name, and a stated sentence as it was
   said (`Domain\Reason`). The screen that shows it names the field beside it,
   so the words need not. A field named among the values, a unique value's
   scope, is named as the language's field names do, since the check that found
   it holds the seed's English labels. */
final readonly class Reasons
{
  public static function worded(Words $words, Violation $violation): string
  {
    if ($violation->reason === Reason::Stated) {
      return $violation->said();
    }
    $values = $violation->values;
    if (isset($values['scope'], $values['key'])) {
      $names = $words->fields($words->language);
      $values['scope'] = $names[$values['type'] . '.' . $values['key']] ?? $names[$values['key']] ?? $values['scope'];
    }

    return $words->get('violation.' . $violation->reason->key(), $values);
  }
}
