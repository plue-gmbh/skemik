<?php

declare(strict_types=1);

namespace Auth;

/* The host's `mail()` answering false, thrown once the answer has left, so it is
   a line of the owner's error log and never a mail about it. */
final class MailRefused extends \RuntimeException
{
}
