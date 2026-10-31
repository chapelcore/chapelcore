<?php

/**
 * HealthServiceTest.php — tests how the health report is put together.
 *
 * Why: the service runs every probe, in order, and builds the HealthStatus that /health
 * returns. One broken probe (one that throws) must not take the whole endpoint down
 * with a 500: it counts as that check being down, and the other checks still run.
 *
 * How it connects: tests src/Health/HealthService.php with stand-in probes; Laravel
 * (1.5) builds the service with the real database, scheduler and storage probes.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Health;

use ChapelCore\Core\Health\HealthProbe;
use ChapelCore\Core\Health\HealthService;
use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Examples;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HealthServiceTest extends TestCase
{
    public function testRunsEveryProbeInOrder(): void
    {
        $service = new HealthService(
            Examples::version(),
            self::probe('database', Status::Ok),
            self::probe('scheduler', Status::Ok),
            self::probe('storage', Status::Ok),
        );

        $health = $service->status();

        self::assertSame(['database', 'scheduler', 'storage'], array_map(static fn(HealthCheck $c): string => $c->name, $health->checks));
        self::assertSame([Status::Ok, 200, '1.0.0'], [$health->status, $health->httpStatus(), $health->version->version]);
    }

    public function testOneFailingProbeChangesTheOverallStatus(): void
    {
        $service = new HealthService(
            Examples::version(),
            self::probe('database', Status::Down, 'Database unavailable'),
            self::probe('scheduler', Status::Ok),
        );

        self::assertSame([Status::Down, 503], [$service->status()->status, $service->status()->httpStatus()]);
    }

    public function testAProbeThatThrowsCountsAsDownAndTheOthersStillRun(): void
    {
        $service = new HealthService(
            Examples::version(),
            self::probe('database', Status::Ok),
            self::throwing('scheduler'),
            self::probe('storage', Status::Ok),
        );

        $checks = $service->status()->checks;

        self::assertCount(3, $checks);
        self::assertSame(['scheduler', Status::Down, null], [$checks[1]->name, $checks[1]->status, $checks[1]->message]);
        self::assertSame(Status::Ok, $checks[2]->status);
    }

    public function testRunsTheProbesAgainOnEveryCall(): void
    {
        $probe = new class implements HealthProbe {
            public int $calls = 0;

            public function name(): string
            {
                return 'database';
            }

            public function check(): HealthCheck
            {
                $this->calls++;

                return new HealthCheck('database', Status::Ok, null);
            }
        };
        $service = new HealthService(Examples::version(), $probe);

        $service->status();
        $service->status();

        self::assertSame(2, $probe->calls);
    }

    private static function probe(string $name, Status $status, ?string $message = null): HealthProbe
    {
        return new class (new HealthCheck($name, $status, $message)) implements HealthProbe {
            public function __construct(private readonly HealthCheck $result) {}

            public function name(): string
            {
                return $this->result->name;
            }

            public function check(): HealthCheck
            {
                return $this->result;
            }
        };
    }

    private static function throwing(string $name): HealthProbe
    {
        return new class ($name) implements HealthProbe {
            public function __construct(private readonly string $name) {}

            public function name(): string
            {
                return $this->name;
            }

            public function check(): HealthCheck
            {
                throw new RuntimeException('heartbeat file unreadable');
            }
        };
    }
}
