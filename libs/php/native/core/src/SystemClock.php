<?php

/**
 * SystemClock.php — the real clock: the server's current time, in UTC.
 *
 * Why: instants are always UTC (DOMAIN-MODEL rule 9), whatever time zone the server or
 * PHP is configured for.
 *
 * How it connects: implements the Clock port; Laravel (1.5) binds it.
 */

declare(strict_types=1);

namespace ChapelCore\Core;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
