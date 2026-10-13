<?php

declare(strict_types=1);

namespace Support;

/* HTML that `Markup` made of a text, typed so a page prints it as it is and
   escapes every string: a string cannot say which of the two it is. */
final readonly class Rendered implements \JsonSerializable, \Stringable
{
  public function __construct(public string $html) {}

  public function jsonSerialize(): string
  {
    return $this->html;
  }

  public function __toString(): string
  {
    return $this->html;
  }
}
