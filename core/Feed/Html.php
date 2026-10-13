<?php

declare(strict_types=1);

namespace Feed;

use Domain\Entity;
use Domain\Locale;
use Storage\Collection;
use Support\Markup;
use Support\Rendered;

/* A record address as a document and a collection address as a listing: the
   format decides the syntax and the route the shape, for `Csv` and `Markdown`
   alike. All four formats stay, each for its consumer class, and cost one filter,
   since all of them render `JsonLd`'s document. A page's title, snippet and
   `og:image` are a mapping onto `headline`, `description` and `image`, written
   once so eight frontends do not each guess, and no `meta_title` field repeats a
   term. A sitemap is the collection listings, page after page by `rel="next"`,
   since the frontend owns its URL space. The text is `Support\Markup`'s, so this
   page and `?render=html` read the same. */
final readonly class Html
{
  public function __construct(private JsonLd $document, private Address $address, private Locale $locale,
    private Markup $markup = new Markup()) {}

  public function contentType(): string
  {
    return 'text/html; charset=utf-8';
  }

  public function record(Entity $entity, Collection $collection): string
  {
    $linking = $this->markup->linking(fn (string $text, string $address): string
      => Markup::anchor($text, $this->address->of($address)));
    $document = $this->document->rendering($linking)->recordDocument($entity, $collection);
    $heading = $this->heading($document);
    $images = $this->images($document);
    $lead = isset($images[0]['contentUrl']) ? array_shift($images) : null;
    $headline = $document['headline'] ?? $document['name'] ?? null;
    $body = ['<h1>' . ($headline instanceof Rendered ? $headline->html : $this->escape($heading)) . '</h1>',
      $lead === null ? '' : $this->object($lead),
      '<article>' . ($document['text'] ?? '') . '</article>',
      $this->definitions([...$document, 'image' => $images], $this->document->labels($collection))];

    return $this->page($heading, $this->head($document), implode("\n", array_filter($body)));
  }

  /** @param list<Entity> $members */
  public function listing(array $members, Collection $collection, int $total, string $address): string
  {
    $list = $this->document->listDocument($members, $collection, $total, $address);
    $rows = [];
    foreach ($list['itemListElement'] as $item) {
      $member = is_array($item['item'] ?? null) ? $item['item'] : [];
      $rows[] = sprintf('<li>%s</li>', $this->link($member));
    }
    $next = Address::next($address, $total);
    $onward = $next === null ? ''
      : sprintf('<a rel="next" href="%s">→</a>', $this->escape($this->address->of($next)));
    $listed = $rows === [] ? '<ul></ul>' : "<ul>\n" . implode("\n", $rows) . "\n</ul>";
    $body = implode("\n", array_filter([sprintf('<h1>%s</h1>', $this->escape((string) $list['name'])),
      sprintf('<p>%d/%d</p>', count($rows), $total), $listed, $onward]));

    return $this->page((string) $list['name'],
      sprintf('<link rel="canonical" href="%s">', $this->escape($address)), $body);
  }

  /** @param array<string, mixed> $document */
  private function head(array $document): string
  {
    $snippet = $document['disambiguatingDescription']
      ?? $document['description'] ?? $document['abstract'] ?? '';
    $snippet = $snippet instanceof Rendered ? $this->plain($snippet) : $snippet;
    $first = $this->images($document)[0] ?? [];
    $head = [sprintf('<link rel="canonical" href="%s">', $this->escape((string) ($document['@id'] ?? '')))];
    if (is_string($snippet) && $snippet !== '') {
      $head[] = sprintf('<meta name="description" content="%s">', $this->escape($snippet));
    }
    if (isset($first['contentUrl'])) {
      $head[] = sprintf('<meta property="og:image" content="%s">',
        $this->escape((string) $first['contentUrl']));
    }

    return implode("\n", $head);
  }

  /* One image or several, each a document of its own.
     @param array<string, mixed> $document @return list<array<string, mixed>> */
  private function images(array $document): array
  {
    $image = $document['image'] ?? [];
    $images = is_array($image) && !array_is_list($image) ? [$image] : (array) $image;

    return array_values(array_filter($images, fn (mixed $one): bool => is_array($one)));
  }

  /** @param array<string, mixed> $document @param array<string, string> $labels by property */
  private function definitions(array $document, array $labels): string
  {
    $rows = [];
    foreach ($document as $property => $value) {
      $shown = !in_array($property, ['@context', '@id', '@type', 'text', 'name', 'headline'], true);
      if ($shown && !in_array($value, [null, []], true)) {
        $rows[] = sprintf('<dt>%s</dt><dd>%s</dd>', $this->escape($labels[$property] ?? $property),
          $this->value($value));
      }
    }

    return $rows === [] ? '' : "<dl>\n" . implode("\n", $rows) . "\n</dl>";
  }

  private function value(mixed $value): string
  {
    if (is_array($value)) {
      return array_is_list($value)
        ? implode(', ', array_map($this->value(...), $value))
        : $this->object($value);
    }

    return match (true) {
      $value instanceof Rendered => $value->html,
      is_bool($value) => $value ? 'true' : 'false',
      $value === null => '',
      default => $this->escape((string) $value),
    };
  }

  /** @param array<string, mixed> $object */
  private function object(array $object): string
  {
    if (isset($object['contentUrl'])) {
      $file = $this->address->file((string) $object['contentUrl'], (string) ($object['encodingFormat'] ?? ''));
      $alt = $object['description'] ?? '';

      return sprintf('<img src="%s" alt="%s">', $this->escape($file),
        $this->escape($alt instanceof Rendered ? $this->plain($alt) : (string) $alt));
    }
    if (isset($object['@id'])) {
      return $this->link($object);
    }
    unset($object['@type'], $object['position']);

    return implode(' ', array_map($this->value(...), $object));
  }

  /** @param array<string, mixed> $record */
  private function link(array $record): string
  {
    $target = $this->address->of((string) ($record['@id'] ?? ''));

    return sprintf('<a href="%s">%s</a>', $this->escape($target), $this->escape($this->heading($record)));
  }

  /** @param array<string, mixed> $document */
  private function heading(array $document): string
  {
    $heading = $document['headline'] ?? $document['name'] ?? $document['@id'] ?? '';

    return match (true) {
      $heading instanceof Rendered => $this->plain($heading),
      is_string($heading) => $heading,
      default => (string) ($document['@id'] ?? ''),
    };
  }

  /* A rendered text as the words it says, where an attribute or a title takes no markup. */
  private function plain(Rendered $text): string
  {
    return html_entity_decode(strip_tags($text->html), ENT_QUOTES, 'UTF-8');
  }

  private function page(string $title, string $head, string $body): string
  {
    return sprintf("<!doctype html>\n<html lang=\"%s\">\n<head>\n<meta charset=\"utf-8\">\n"
      . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>%s</title>\n%s\n"
      . "</head>\n<body>\n%s\n</body>\n</html>\n", $this->escape($this->locale->tag),
      $this->escape($title), $head, $body);
  }

  private function escape(string $text): string
  {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }
}
