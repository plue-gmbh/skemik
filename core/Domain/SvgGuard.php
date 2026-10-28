<?php

declare(strict_types=1);

namespace Domain;

/* The upload check: an SVG passes or is refused whole, never repaired, because a
   sanitiser owns its parser's output forever. An allowlist, since a list of
   dangerous things has to enumerate what it has not met yet. Parsed with network
   access off, and one declaring anything of its own is refused before any content
   rule runs: its entities are XXE and the billion laughs, and an attribute it
   gives an element by default is one a browser adds and no rule here reads — at
   the cost of old Illustrator exports that spell their namespaces as entities.
   Editor metadata (Inkscape, Sodipodi, RDF, Adobe, Sketch) is inert and admitted,
   as Illustrator's `data-name` and Canva's `zoomAndPan` are, or most exported
   logos would fail; every other namespace, XHTML first, is not.
   A drawing needs nothing from the network, so no `href` leaves the document and
   no `url()` leaves it either. The CSP on the response is the second lock. */
final readonly class SvgGuard
{
  private const SVG = 'http://www.w3.org/2000/svg';
  private const XLINK = 'http://www.w3.org/1999/xlink';
  private const XML = 'http://www.w3.org/XML/1998/namespace';
  private const EDITOR_NAMESPACES = [
    'http://www.inkscape.org/namespaces/inkscape',
    'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd',
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'http://creativecommons.org/ns#',
    'http://purl.org/dc/elements/1.1/',
    'http://www.bohemiancoding.com/sketch/ns',
    'http://ns.adobe.com/',
  ];
  /* No animation yet: a drawing that moves is admitted by weighing its elements
     one by one, which is a later pass and not a refusal on principle. */
  private const ELEMENTS =
    'svg g defs symbol use title desc metadata style path rect circle ellipse line polyline '
    . 'polygon text tspan textPath linearGradient radialGradient stop clipPath mask pattern '
    . 'marker image filter feBlend feColorMatrix feComponentTransfer feComposite feConvolveMatrix '
    . 'feDiffuseLighting feDisplacementMap feDistantLight feDropShadow feFlood feFuncA feFuncB '
    . 'feFuncG feFuncR feGaussianBlur feImage feMerge feMergeNode feMorphology feOffset '
    . 'fePointLight feSpecularLighting feSpotLight feTile feTurbulence';
  private const ATTRIBUTES =
    'id class style lang tabindex role focusable version baseProfile zoomAndPan x y x1 x2 y1 y2 cx cy r rx '
    . 'ry fx fy fr dx dy d points width height viewBox preserveAspectRatio transform href fill '
    . 'fill-opacity fill-rule stroke stroke-width stroke-linecap stroke-linejoin '
    . 'stroke-miterlimit stroke-dasharray stroke-dashoffset stroke-opacity opacity color display '
    . 'visibility overflow clip-path clip-rule clipPathUnits mask maskUnits maskContentUnits '
    . 'mask-type filter filterUnits primitiveUnits gradientUnits gradientTransform spreadMethod '
    . 'offset stop-color stop-opacity patternUnits patternContentUnits patternTransform '
    . 'markerWidth markerHeight markerUnits refX refY orient marker-start marker-mid marker-end '
    . 'font-family font-size font-weight font-style font-variant font-stretch text-anchor '
    . 'dominant-baseline alignment-baseline baseline-shift letter-spacing word-spacing '
    . 'text-decoration rotate textLength lengthAdjust startOffset method spacing side '
    . 'writing-mode direction mix-blend-mode isolation paint-order vector-effect shape-rendering '
    . 'text-rendering image-rendering color-interpolation color-interpolation-filters '
    . 'enable-background in in2 result stdDeviation mode type values operator k1 k2 k3 k4 '
    . 'flood-color flood-opacity radius baseFrequency numOctaves seed stitchTiles scale '
    . 'xChannelSelector yChannelSelector tableValues slope intercept amplitude exponent order '
    . 'kernelMatrix divisor bias targetX targetY edgeMode preserveAlpha surfaceScale '
    . 'diffuseConstant specularConstant specularExponent lighting-color azimuth elevation z '
    . 'pointsAtX pointsAtY pointsAtZ limitingConeAngle kernelUnitLength';

  /** @return list<Violation> empty when the file may be kept */
  public function check(string $svg): array
  {
    $document = new \DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $svg !== '' && $document->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);
    if (!$loaded) {
      return [$this->refuse('svg', 'the file is not well-formed XML')];
    }
    if ($document->doctype?->internalSubset !== null) {
      return [$this->refuse('svg', 'the file declares entities or attributes of its own')];
    }
    $root = $document->documentElement;
    if ($root?->namespaceURI !== self::SVG || $root->localName !== 'svg') {
      return [$this->refuse('svg', 'the file is not an SVG drawing')];
    }

    $nodes = iterator_to_array((new \DOMXPath($document))->query('//node()'));

    return array_merge(...array_map($this->checkNode(...), $nodes));
  }

  /** @return list<Violation> */
  private function checkNode(\DOMNode $node): array
  {
    if ($node instanceof \DOMProcessingInstruction) {
      return [$this->refuse($node->nodeName, sprintf('<?%s?> can fetch', $node->nodeName))];
    }
    /* An editor's own element is never rendered, and neither is anything on it. */
    if (!$node instanceof \DOMElement || $this->isEditorMetadata($node->namespaceURI)) {
      return [];
    }
    $violations = $this->checkElement($node);
    foreach ($node->attributes as $attribute) {
      $violations = [...$violations, ...$this->checkAttribute($attribute, $node->nodeName)];
    }

    return $violations;
  }

  /** @return list<Violation> */
  private function checkElement(\DOMElement $element): array
  {
    $name = $element->nodeName;
    if ($element->namespaceURI !== self::SVG) {
      return [$this->refuse($name, sprintf('<%s> is not part of a drawing', $name))];
    }
    if (!in_array($element->localName, explode(' ', self::ELEMENTS), true)) {
      return [$this->refuse($name, sprintf('<%s> is not a drawing element', $name))];
    }
    if ($element->localName === 'style' && $this->reachesOut($element->textContent)) {
      return [$this->refuse($name, 'the stylesheet ' . $this->reach($element->textContent))];
    }

    return [];
  }

  /** @return list<Violation> */
  private function checkAttribute(\DOMAttr $attribute, string $element): array
  {
    $name = $attribute->nodeName;
    $namespace = $attribute->namespaceURI;
    if (str_starts_with(strtolower($attribute->localName), 'on')) {
      return [$this->refuse($element, sprintf('%s on <%s> runs script', $name, $element))];
    }
    $known = match ($namespace) {
      null => preg_match('/^(aria|data)-/', $name) === 1 || in_array($name, $this->drawingAttributes(), true),
      self::XML => in_array($attribute->localName, ['space', 'lang'], true),
      self::XLINK => in_array($attribute->localName, ['href', 'title'], true),
      default => $this->isEditorMetadata($namespace),
    };
    if (!$known) {
      return [$this->refuse($element, sprintf('%s on <%s> is not for drawing', $name, $element))];
    }
    $isLink = $attribute->localName === 'href' && !str_starts_with(ltrim($attribute->value), '#');
    if ($isLink || $this->reachesOut($attribute->value)) {
      return [$this->refuse($element, sprintf('%s on <%s> %s', $name, $element, $this->reach($attribute->value)))];
    }

    return [];
  }

  /* CSS escapes are refused outright: `u\72l(` is a `url(` no pattern sees. */
  private function reachesOut(string $css): bool
  {
    return preg_match('/@import|\\\\|image-set\(|url\(\s*+[\'"]?+\s*+(?!#)/i', $css) === 1;
  }

  /* An embedded file is refused as one fetched is, and said apart: nothing
     leaves the document for it, but its bytes would be kept without being read. */
  private function reach(string $value): string
  {
    return preg_match('/(^|url\(\s*+[\'"]?+)\s*+data:/i', $value) === 1
      ? 'embeds a file, which this check does not read' : 'reaches outside the file';
  }

  /** @return list<string> */
  private function drawingAttributes(): array
  {
    return explode(' ', self::ATTRIBUTES);
  }

  private function isEditorMetadata(?string $namespace): bool
  {
    foreach (self::EDITOR_NAMESPACES as $editor) {
      if ($namespace !== null && str_starts_with($namespace, $editor)) {
        return true;
      }
    }

    return false;
  }

  private function refuse(string $where, string $reason): Violation
  {
    return Violation::stated($where, $reason);
  }
}
