<?php

/**
 * Clock.php — the port through which the core asks what time it is.
 *
 * Why: rules that depend on time (the 5-minute heartbeat, certificate expiry, log
 * timestamps) take the time from here rather than calling `new DateTimeImmutable()`
 * themselves, so tests can fix the moment exactly.
 *
 * How it connects: SystemClock is the real implementation; tests use FixedClock.
 * SchedulerProbe uses it now; the doctor and logging do in checkpoints 4 and 5.
 */

declare(strict_types=1);

namespace ChapelCore\Core;

use DateTimeImmutable;

interface Clock
{
    /** The current instant, in UTC. */
    public function now(): DateTimeImmutable;
}
