<?php

declare(strict_types=1);

namespace Auth;

/* Why a relay failed, one of a closed set, so the general screen words it in the
   reader's language from `relay.{value}` while the log line stays English. */
enum RelayFault: string
{
  case Unreachable = 'unreachable';
  case NoStartTls = 'no_starttls';
  case Handshake = 'handshake';
  case Login = 'login';
  case Address = 'address';
  case Closed = 'closed';
  case Timeout = 'timeout';
  case Unexpected = 'unexpected';
}
