<?php

declare(strict_types=1);

namespace Support;

/* Renders one file under `view/` to a string, so a controller answers with a
   body and `index.php` stays the only writer of output. A template sees the
   values it was handed, `$template` for escaping and for partials, and the
   renderer's `$__path` and `$__values`. Escaping is `escape()` at the boundary
   and nowhere earlier: a value escaped twice shows `&amp;` to an organisation that typed `&`.
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
    assert(!isset($values['__path']) && !isset($values['__values']),
      'a value named __path or __values is the renderer\'s own');
    /** @psalm-taint-escape extract */
    $handed = $values; /* its keys are the caller's literals, so no request value names a variable */
    $render = function (string $__path, array $__values): void {
      /* Without overwriting, so where assertions are off a value can never change which file is required. */
      extract($__values, EXTR_SKIP);
      $template = $this;
      require $__path;
    };
    $level = ob_get_level();
    ob_start();
    try {
      $render($path, $handed);
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
