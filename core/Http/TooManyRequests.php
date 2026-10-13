<?php

declare(strict_types=1);

namespace Http;

/* A bucket of `Storage\RateLimit` is spent: `429`, and it ends with its window. */
final class TooManyRequests extends \RuntimeException {}
