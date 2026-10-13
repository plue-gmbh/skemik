<?php

declare(strict_types=1);

namespace Support;

/* Renders one file under `view/` to a string, so a controller answers with a
   body and `index.php` stays the only writer of output. A template sees the
   values it was handed and `$template`, for escaping and for partials, and
   nothing else. Escaping is `escape()` at the boundary and nowhere earlier: a
   value escaped twice shows `&amp;` to a house that typed `&`.
   A failure halfway through a template discards what it wrote, so an error page
   never arrives inside half of the page that failed.
   A template answers nothing when requested directly — it returns while
   `$template` is unset — which is the invariant `index.php` states for every
   file but itself. */
final readonly class Template
{
  public function __construct(private string $directory) {}

  /**
   * @param array<string, object|array<array-key, object|string|int|float|bool|null>|string|int|float|bool|null> $values
   */
  public function render(string $name, array $values = []): string
  {
    $path = $this->directory . '/' . $name . '.php';
    if (!is_file($path)) {
      throw new \LogicException(sprintf('no template %s', $name));
    }
    assert(!isset($values['template']), 'a value named template is the renderer');
    /* No named parameters, which a value of the same name would silently lose to. */
    $render = function (): void {
      extract(func_get_arg(1));
      $template = $this;
      require func_get_arg(0);
    };
    $level = ob_get_level();
    ob_start();
    try {
      $render($path, $values);
    } catch (\Throwable $failure) {
      while (ob_get_level() > $level) {
        ob_end_clean();
      }
      throw $failure;
    }

    return (string) ob_get_clean();
  }

  /* The file's time in the address, since `view/` is served with no caching
     headers and a phone would otherwise keep an old stylesheet for hours. */
  public function asset(string $name): string
  {
    return sprintf('/view/%s?v=%d', $name, filemtime($this->directory . '/' . $name));
  }

  public function escape(string|int|float|null $text): string
  {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
  }
}
