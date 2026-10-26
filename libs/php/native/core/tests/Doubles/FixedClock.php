<?php

/**
 * FixedClock.php — a clock that says whatever time the test sets.
 *
 * Why: rules that depend on time (the 5-minute heartbeat now, certificate expiry in
 * checkpoint 5) must be tested at exact moments, such as 4m59s and 5m01s, which a real
 * clock can't give.
 *
 * How it connects: implements the core's Clock port; tests pass it wherever the
 * production code takes a Clock.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doubles;

use ChapelCore\Core\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-10-25T12:00:00Z')
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
