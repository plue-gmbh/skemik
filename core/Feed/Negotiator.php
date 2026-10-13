<?php

declare(strict_types=1);

namespace Feed;

/* Chooses the serialiser from the path suffix first, then `Accept`. Explicit
   beats implicit so a copied URL always yields what it yielded before, and a
   suffix that is none of the four is not this address at all — `Http\Router`
   leaves it in the segment, where it becomes a collection nobody has. `Accept`
   is read for the first of the four it names and its weights are not, which is
   enough to tell a browser from `fetch`. A browser saves `.csv` rather than show
   it: `text/csv` is what the body is, and a spreadsheet reads the address as a
   table either way. */
final readonly class Negotiator
{
  private const ACCEPTED = ['jsonld' => 'ld+json', 'html' => 'text/html', 'csv' => 'text/csv',
    'md' => 'markdown'];

  public function __construct(
    private JsonLd $jsonLd,
    private Html $html,
    private Csv $csv,
    private Markdown $markdown,
  ) {}

  public function choose(?string $suffix, ?string $accept): JsonLd|Html|Csv|Markdown
  {
    return match ($suffix ?? $this->offered($accept)) {
      'html' => $this->html,
      'csv' => $this->csv,
      'md' => $this->markdown,
      default => $this->jsonLd,
    };
  }

  /* The one `Accept` names first; JSON-LD answers a caller that names none of
     them, which is the format the whole model is for. */
  private function offered(?string $accept): string
  {
    $at = [];
    foreach (self::ACCEPTED as $format => $media) {
      $found = stripos((string) $accept, $media);
      if ($found !== false) {
        $at[$format] = $found;
      }
    }
    asort($at);

    return array_key_first($at) ?? 'jsonld';
  }
}
