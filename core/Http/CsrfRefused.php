<?php

declare(strict_types=1);

namespace Http;

/* A state change that did not come from a page of this session, or a login
   asked for from a page of another site: `403`, and the page says to reload
   rather than what was missing. */
final class CsrfRefused extends \RuntimeException {}
