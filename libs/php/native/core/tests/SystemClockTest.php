<?php

/**
 * SystemClockTest.php — tests the real clock.
 *
 * Why: instants are stored and sent in UTC (DOMAIN-MODEL rule 9). The server's time zone
 * setting must never leak into a timestamp.
 *
 * How it connects: tests src/SystemClock.php, the production implementation of the
 * Clock port.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests;

use ChapelCore\Core\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testGivesTheCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testIsAlwaysInUtc(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/Denver');
        try {
            self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
