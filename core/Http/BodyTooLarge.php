<?php

declare(strict_types=1);

namespace Http;

/* A body past what the host takes (`Request::bodyLimit`): `413`, before a route
   is asked, rather than a `500` at PHP's memory limit. */
final class BodyTooLarge extends \RuntimeException {}
