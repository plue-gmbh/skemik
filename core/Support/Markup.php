<?php

declare(strict_types=1);

namespace Support;

/* The one Markdown renderer, written rather than depended on, the same under the
   feed's `.html`, a document asked rendered and the record screen, and below
   everything because the feed and the admin both render and neither may reach
   the other. A heading is the level written, never shifted, since the record's
   name is the page's `<h1>`, and a block wherever it stands, as a list is after
   a line of prose: every other renderer reads the same text so. An asterisk
   beside a space is a footnote's and no emphasis. An image in prose stays its
   characters, an image being a field with a description somebody was asked for.
   A link's scheme is the caller's to allow, a link field's where nobody says,
   and a link to a record is `skemik:` and its id, which a slug change leaves
   alone. A fence of ``` lines holds its lines as typed, blank ones too, escaped
   and never marked. A duration given words leaves as *1 month*, and ISO 8601
   otherwise. */
final readonly class Markup
{
  public const RECORD = 'skemik:';
  private const LINK = '/(?<!!)\[([^\]]+)\]\(([^)\s]+)\)/';
  private const FENCE = '/^```([^`\n]*)$\n((?:.*?\n)?)```[ \t]*$/ms';
  private const DURATION = '/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/';
  private const DURATION_UNITS = ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'];

  /**
   * @param ?\Closure(string, string): string $link the markup for a link's text, already
   *   safe, at its decoded address; `anchor` where none is given
   * @param ?Words $words the language a duration is worded in, or none
   */
  public function __construct(private ?\Closure $link = null, private ?Words $words = null) {}

  public function linking(\Closure $link): self
  {
    return clone($this, ['link' => $link]);
  }

  /* Markdown with each link's address answered by `$to`, the words alone where
     it answers null; the pattern is the renderer's, so both see the same links.
     @param \Closure(string): ?string $to */
  public static function relinked(string $markdown, \Closure $to): string
  {
    return (string) preg_replace_callback(self::LINK, function (array $found) use ($to): string {
      $address = $to($found[2]);

      return $address === null ? $found[1] : '[' . $found[1] . '](' . $address . ')';
    }, $markdown);
  }

  /* The fences are cut out first, since a blank line inside one is no block's end. */
  public function prose(string $markdown): Rendered
  {
    $parts = preg_split(self::FENCE, str_replace(["\r\n", "\r"], "\n", $markdown), -1,
      PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $blocks = [];
    foreach (array_chunk($parts, 3) as $chunk) {
      $blocks[] = $this->blocks($chunk[0]);
      $blocks[] = isset($chunk[2]) ? $this->fence($chunk[1], $chunk[2]) : '';
    }

    return new Rendered(implode("\n", array_filter($blocks)));
  }

  /* The blocks between two fences, split on blank lines, a heading or a list after prose standing alone. */
  private function blocks(string $text): string
  {
    $blocks = [];
    $spaced = preg_replace(['/^#{1,6} .+$/m', '/^(?!- |\d+\. |> | )(.+)\n(?=- |1\. )/m'],
      ["\n\n$0\n\n", "$1\n\n"], $text);
    foreach (preg_split('/\n{2,}/', trim((string) $spaced)) ?: [] as $block) {
      $blocks[] = $this->block(trim($block));
    }

    return implode("\n", array_filter($blocks));
  }

  /* As CommonMark writes it, the first word a fence names as the code's class, so a highlighter finds it;
     the code ends with its own line break, or is empty. */
  private function fence(string $info, string $code): string
  {
    $language = explode(' ', trim(str_replace("\t", ' ', $info)), 2)[0];
    $class = $language === '' ? '' : ' class="language-' . self::escape($language) . '"';

    return '<pre><code' . $class . '>' . self::escape($code) . '</code></pre>';
  }

  public function text(string $plain): Rendered
  {
    $paragraphs = array_filter(array_map(trim(...), preg_split('/\n\s*\n/', trim($plain)) ?: []),
      fn (string $paragraph): bool => $paragraph !== '');

    return new Rendered(implode("\n", array_map(fn (string $paragraph): string
      => '<p>' . nl2br($this->inline($paragraph), false) . '</p>', $paragraphs)));
  }

  public function line(string $plain): Rendered
  {
    return new Rendered($this->inline(trim($plain)));
  }

  public function duration(string $iso): string
  {
    if ($this->words === null || preg_match(self::DURATION, $iso, $parts) !== 1) {
      return $iso;
    }
    $shown = [];
    foreach (self::DURATION_UNITS as $index => $unit) {
      $n = $parts[$index + 1] ?? '';
      if ($n !== '') {
        $shown[] = $this->words->count('duration', $unit, (int) $n);
      }
    }

    return $shown === [] ? $iso : implode(' ', $shown);
  }

  public static function anchor(string $text, string $address): string
  {
    $allowed = preg_match('#^(https?://|mailto:|tel:|sms:|/(?!/)|\.\.?/)#i', $address) === 1;

    return $allowed ? sprintf('<a href="%s">%s</a>', self::escape($address), $text) : $text;
  }

  public static function escape(string $text): string
  {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  private function block(string $block): string
  {
    $lines = explode("\n", $block);
    $heading = [];
    if (count($lines) === 1 && preg_match('/^(#{1,6}) (.+)$/', $lines[0], $heading) === 1) {
      return sprintf('<h%1$d>%2$s</h%1$d>', strlen($heading[1]), $this->inline($heading[2]));
    }
    if (str_starts_with($block, '- ') || preg_match('/^\d+\. /', $block) === 1) {
      return $this->items($block, str_starts_with($block, '- ') ? 'ul' : 'ol');
    }
    if (str_starts_with($block, '> ')) {
      $quoted = (string) preg_replace('/^> ?/m', '', $block);

      return '<blockquote><p>' . $this->inline($quoted) . '</p></blockquote>';
    }

    /* A line ending in two spaces breaks the line, as CommonMark has it; any other
       line break inside a paragraph is a space. */
    $paragraph = preg_replace(['/ {2,}\n/', '/\n/'], ['<br>', ' '], $this->inline(implode("\n", $lines)));

    return $block === '' ? '' : '<p>' . $paragraph . '</p>';
  }

  /* An item runs to the next marker, so one wrapped over lines is one item. */
  private function items(string $block, string $tag): string
  {
    $items = array_map(fn (string $item): string => '<li>'
      . $this->inline((string) preg_replace(['/^(- |\d+\. )/', '/\s*\n\s*/'], ['', ' '], $item)) . '</li>',
      preg_split('/\n(?=- |\d+\. )/', $block) ?: []);

    return sprintf('<%1$s>%2$s</%1$s>', $tag, implode('', $items));
  }

  /* Escaped first, so every rule below writes into text that is already safe. */
  private function inline(string $text): string
  {
    $rules = [
      '/`([^`]+)`/' => '<code>$1</code>',
      '/\*\*(?=\S)([^*]+)(?<=\S)\*\*/' => '<strong>$1</strong>',
      '/(?<!\*)\*(?=\S)([^*]+)(?<=\S)\*(?!\*)/' => '<em>$1</em>',
    ];
    $inline = (string) preg_replace(array_keys($rules), array_values($rules), self::escape($text));
    $link = $this->link ?? self::anchor(...);

    return (string) preg_replace_callback(self::LINK,
      fn (array $found): string => $link($found[1], html_entity_decode($found[2], ENT_QUOTES, 'UTF-8')),
      $inline);
  }
}
