<?php

declare(strict_types=1);

namespace Support;

/* The self-test over the payload's files, `index.php`'s claim: every other file does
   nothing when included or requested directly, which is what stands where a host ignores
   the deny rules. A file passes in one of three shapes, read from its tokens and never
   run: declarations alone; a `return` whose value calls nothing, a language's words or a
   migration's closure; or a template's guard first. Anything else at the top of a file, a
   call, an `echo`, a line of HTML, is the second entry point the rule forbids. It runs once
   per state of the files, which no request changes, since a scan on every page was most of
   each page's time. */
final readonly class Payload
{
  private const FOLDERS = ['core', 'view', 'language', 'migrations'];
  private const GUARD = 'if(!isset($template)){return;}';
  private const DECLARING = [T_DECLARE, T_NAMESPACE, T_USE];
  private const MODIFIERS = [T_ATTRIBUTE, T_FINAL, T_ABSTRACT, T_READONLY];
  private const DECLARED = [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT];
  private const CALLING = [T_NEW, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL,
    T_EXIT, T_PRINT, '`'];
  private const OPENING = ['{', '[', '(', T_ATTRIBUTE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES];
  private const MARKER = 'checked.txt';

  public function __construct(private string $root, private string $data) {}

  /** @return list<string> each file that runs something when included, from the root */
  public function running(): array
  {
    return $this->found(__FUNCTION__, fn (): array => array_values(array_filter($this->files(self::FOLDERS, ['php']),
      fn (string $path): bool => !$this->inert($this->root . '/' . $path))));
  }

  /* What `$scan` found, or nothing where it found nothing on the files as they stand. Their
     times are read before the scan, so a file saved during it shows as moved, and a pass is
     not kept while a file is from this second, where a second save would leave its time.
     @param \Closure(): list<string> $scan
     @return list<string> */
  public function found(string $check, \Closure $scan): array
  {
    [$now, $times] = [time(), $this->times()];
    $fingerprint = md5(serialize($times));
    $marker = $this->data . '/' . self::MARKER;
    $lines = is_file($marker) ? file($marker, FILE_IGNORE_NEW_LINES) ?: [] : [];
    $passed = ($lines[0] ?? '') === $fingerprint ? array_slice($lines, 1) : [];
    if (in_array($check, $passed, true)) {
      return [];
    }
    $found = $scan();
    if ($found === [] && max([0, ...$times]) < $now) {
      file_put_contents($marker, implode("\n", [$fingerprint, ...$passed, $check]) . "\n");
    }
    return $found;
  }

  /** @return array<string, int> each file's modification time by its path from the root */
  private function times(): array
  {
    $paths = $this->files(self::FOLDERS, ['php']);
    return array_combine($paths,
      array_map(fn (string $path): int => (int) filemtime($this->root . '/' . $path), $paths));
  }

  /**
   * @param list<string> $folders
   * @param list<string> $extensions
   * @return list<string> from the root
   */
  private function files(array $folders, array $extensions): array
  {
    $found = [];
    foreach (array_filter($folders, fn (string $folder): bool => is_dir($this->root . '/' . $folder)) as $folder) {
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
        $this->root . '/' . $folder, \FilesystemIterator::SKIP_DOTS));
      foreach ($files as $file) {
        if (in_array($file->getExtension(), $extensions, true)) {
          $found[] = substr($file->getPathname(), strlen($this->root) + 1);
        }
      }
    }
    return $found;
  }

  private function inert(string $path): bool
  {
    /* A string's inside is dropped with the whitespace, since `is(')')` compares text
       and an interpolated query's `)` would close what it never opened. */
    $tokens = array_values(array_filter(\PhpToken::tokenize((string) file_get_contents($path)),
      fn (\PhpToken $token): bool => ($token->is(T_OPEN_TAG) || !$token->isIgnorable())
        && !$token->is(T_ENCAPSED_AND_WHITESPACE)));
    if (($tokens[0] ?? null)?->is(T_OPEN_TAG) !== true) {
      return false;
    }
    for ($at = 1; $at < count($tokens); $at = $this->declared($tokens, $at)) {
      if (!$tokens[$at]->is([...self::DECLARING, ...self::MODIFIERS, ...self::DECLARED])) {
        return $this->guarded($tokens, $at) || $this->returnsInert($tokens, $at);
      }
    }
    return true;
  }

  /** @param list<\PhpToken> $tokens */
  private function declared(array $tokens, int $at): int
  {
    if ($tokens[$at]->is(self::DECLARING)) {
      return $this->statementEnd($tokens, $at);
    }
    while ($tokens[$at]->is(self::MODIFIERS)) {
      $at = $tokens[$at]->is(T_ATTRIBUTE) ? $this->balanced($tokens, $at) : $at + 1;
    }
    while (isset($tokens[$at]) && !$tokens[$at]->is('{')) {
      $at++;
    }
    return $this->balanced($tokens, $at);
  }

  /** @param list<\PhpToken> $tokens */
  private function guarded(array $tokens, int $at): bool
  {
    $opening = array_slice($tokens, $at, 12);
    return implode('', array_map(fn (\PhpToken $token): string => $token->text, $opening)) === self::GUARD;
  }

  /* A value built by a call would run the call as the file is included. A
     closure's parameters and its `use` are the parentheses that call nothing, and
     its body runs only when the including code asks. */
  /** @param list<\PhpToken> $tokens */
  private function returnsInert(array $tokens, int $at): bool
  {
    if (!$tokens[$at]->is(T_RETURN)) {
      return false;
    }
    for ($end = $this->statementEnd($tokens, $at), $at++; $at < $end; $at++) {
      $at = $tokens[$at]->is('{') ? $this->balanced($tokens, $at) - 1 : $at;
      if ($tokens[$at]->is(self::CALLING)
        || ($tokens[$at]->is('(') && !$tokens[$at - 1]->is([T_FUNCTION, T_FN, T_USE]))) {
        return false;
      }
    }
    return true;
  }

  /** @param list<\PhpToken> $tokens */
  private function statementEnd(array $tokens, int $at): int
  {
    for (; isset($tokens[$at]) && !$tokens[$at]->is(';'); $at++) {
      $at = $tokens[$at]->is(self::OPENING) ? $this->balanced($tokens, $at) - 1 : $at;
    }
    return $at + 1;
  }

  /** @param list<\PhpToken> $tokens the one at `$at` opening what the index after its match ends */
  private function balanced(array $tokens, int $at): int
  {
    for ($depth = 0; isset($tokens[$at]); $at++) {
      $depth += $tokens[$at]->is(self::OPENING) ? 1 : ($tokens[$at]->is(['}', ']', ')']) ? -1 : 0);
      if ($depth === 0) {
        return $at + 1;
      }
    }
    return $at;
  }
}
