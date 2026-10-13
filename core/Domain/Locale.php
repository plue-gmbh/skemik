<?php

declare(strict_types=1);

namespace Domain;

use Domain\Exceptions\MalformedValue;
use Profile\ClosedList;

/* BCP-47, validated. The tag the spine stores, the address carries as a segment
   and the export emits as `inLanguage` — one spelling, never shortened.
   Casing is made canonical here because the tag is half of the unique key: `de-de`
   and `de-DE` stored side by side would be two languages holding one slug.
   The language and the region are read against the lists a field's language and
   country take, so `zz` is no language and `es-419` no region: a locale is one a
   field could name. */
final readonly class Locale
{
  private const TAG = '/^([a-z]{2})(?:-([a-z]{4}))?(?:-([a-z]{2}))?((?:-(?:[a-z\d]{5,8}|\d[a-z\d]{3}))*)$/i';

  public string $tag;

  public function __construct(string $tag)
  {
    $this->tag = $this->canonical($tag);
    assert($this->canonical('SR-latn-rs') === 'sr-Latn-RS', 'a tag is not cased canonically');
  }

  private function canonical(string $tag): string
  {
    if (preg_match(self::TAG, $tag, $parts) !== 1) {
      throw MalformedValue::named('locale', $tag);
    }
    [, $language, $script, $region, $variants] = $parts;
    $listed = in_array(strtolower($language), ClosedList::Language->codes(), true)
      && ($region === '' || in_array(strtoupper($region), ClosedList::Country->codes(), true));
    if (!$listed) {
      throw MalformedValue::named('locale', $tag);
    }

    return strtolower($language)
      . ($script === '' ? '' : '-' . ucfirst(strtolower($script)))
      . ($region === '' ? '' : '-' . strtoupper($region))
      . strtolower($variants);
  }
}
