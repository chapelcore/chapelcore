<?php

/**
 * SchedulerProbeTest.php — tests the `scheduler` health check.
 *
 * Why: cron runs the heartbeat every minute; if it hasn't been seen for more than 5
 * minutes, scheduled work has stopped and /health says degraded (DOMAIN-MODEL 0.4 §4).
 * The edges (exactly 5 minutes, never seen, a clock slightly behind) are where such
 * rules usually break.
 *
 * How it connects: tests src/Health/SchedulerProbe.php with a FixedClock and an
 * in-memory HeartbeatStore; Laravel (1.5) stores the real heartbeat.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Health;

use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Health\SchedulerProbe;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchedulerProbeTest extends TestCase
{
    /** @return iterable<string, array{string|null, Status}> */
    public static function heartbeats(): iterable
    {
        yield 'just now' => ['2026-10-25T12:00:00Z', Status::Ok];
        yield '4m59s ago' => ['2026-10-25T11:55:01Z', Status::Ok];
        yield 'exactly 5 minutes ago' => ['2026-10-25T11:55:00Z', Status::Ok];
        yield '5m01s ago' => ['2026-10-25T11:54:59Z', Status::Degraded];
        yield 'yesterday' => ['2026-10-24T12:00:00Z', Status::Degraded];
        yield 'never seen' => [null, Status::Degraded];
        yield 'a second in the future (clock skew)' => ['2026-10-25T12:00:01Z', Status::Ok];
    }

    #[DataProvider('heartbeats')]
    public function testStatusDependsOnTheLastHeartbeat(?string $lastBeat, Status $expected): void
    {
        $probe = new SchedulerProbe(self::store($lastBeat), new FixedClock('2026-10-25T12:00:00Z'));

        self::assertSame($expected, $probe->check()->status);
    }

    public function testExplainsADegradedCheckGenerically(): void
    {
        $check = (new SchedulerProbe(self::store(null), new FixedClock()))->check();

        self::assertSame(['scheduler', 'Scheduled tasks have not run recently'], [$check->name, $check->message]);
    }

    public function testAPassingCheckSaysNothing(): void
    {
        $check = (new SchedulerProbe(self::store('2026-10-25T12:00:00Z'), new FixedClock()))->check();

        self::assertNull($check->message);
    }

    public function testIsNamedScheduler(): void
    {
        self::assertSame('scheduler', (new SchedulerProbe(self::store(null), new FixedClock()))->name());
    }

    private static function store(?string $lastBeat): HeartbeatStore
    {
        return new class ($lastBeat === null ? null : new DateTimeImmutable($lastBeat)) implements HeartbeatStore {
            public function __construct(private readonly ?DateTimeImmutable $lastBeat) {}

            public function lastBeat(): ?DateTimeImmutable
            {
                return $this->lastBeat;
            }
        };
    }
}
