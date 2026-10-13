<?php

declare(strict_types=1);

namespace Http;

/* A write that did not say which version it read: `428`, never a blind overwrite. */
final class PreconditionRequired extends \RuntimeException {}
