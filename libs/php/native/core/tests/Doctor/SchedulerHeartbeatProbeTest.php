<?php

/**
 * SchedulerHeartbeatProbeTest.php — tests the doctor's `scheduler.heartbeat` check.
 *
 * Why: the same rule as the public `scheduler` health check: a heartbeat in the last
 * 5 minutes (DOMAIN-MODEL §5.8). The doctor also says when the last one was, and points
 * to the cron line that is usually missing. On a first deploy there is no heartbeat
 * yet, which is degraded and doesn't block the deploy.
 *
 * How it connects: tests src/Doctor/SchedulerHeartbeatProbe.php, which reuses the
 * health check's SchedulerProbe, with a stand-in HeartbeatStore and a FixedClock
 * (2026-10-25T12:00:00Z).
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\SchedulerHeartbeatProbe;
use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SchedulerHeartbeatProbeTest extends TestCase
{
    public function testARecentHeartbeatIsOkAndShown(): void
    {
        $check = (new SchedulerHeartbeatProbe(self::store('2026-10-25T11:58:00Z'), new FixedClock()))->check();

        self::assertSame(
            ['scheduler.heartbeat', Status::Ok, 'Last heartbeat at 2026-10-25T11:58:00.000Z', null],
            [$check->name, $check->status, $check->message, $check->fix],
        );
    }

    public function testExactlyFiveMinutesIsStillOk(): void
    {
        self::assertSame(Status::Ok, (new SchedulerHeartbeatProbe(self::store('2026-10-25T11:55:00Z'), new FixedClock()))->check()->status);
    }

    public function testAnOldHeartbeatIsDegradedWithTheFix(): void
    {
        $check = (new SchedulerHeartbeatProbe(self::store('2026-10-25T11:54:59Z'), new FixedClock()))->check();

        self::assertSame(
            [Status::Degraded, 'Last heartbeat at 2026-10-25T11:54:59.000Z, more than 5 minutes ago', 'Add the cron line from the hosting runbook §8.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testNoHeartbeatYetIsDegraded(): void
    {
        $check = (new SchedulerHeartbeatProbe(self::store(null), new FixedClock()))->check();

        self::assertSame(
            [Status::Degraded, 'No heartbeat has been recorded yet', 'Add the cron line from the hosting runbook §8.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    private static function store(?string $lastBeat): HeartbeatStore
    {
        return new readonly class ($lastBeat === null ? null : new DateTimeImmutable($lastBeat)) implements HeartbeatStore {
            public function __construct(private ?DateTimeImmutable $lastBeat) {}

            public function lastBeat(): ?DateTimeImmutable
            {
                return $this->lastBeat;
            }
        };
    }
}
