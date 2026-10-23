<?php

/**
 * HealthStatusTest.php — tests the body of GET {apiBase}/health.
 *
 * Why: deploys poll /health and roll back on failure, so the overall status (worst of
 * the checks) and its HTTP status (200 for ok or degraded, 503 for down) are rules the
 * pipeline depends on (DOMAIN-MODEL §4).
 *
 * How it connects: tests src/Model/HealthStatus.php; HealthService builds one in
 * checkpoint 3, and the Serializer writes it as JSON.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\HealthStatus;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Examples;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HealthStatusTest extends TestCase
{
    public function testAllOkIsOkAndAnswers200(): void
    {
        $health = Examples::healthy();

        self::assertSame([Status::Ok, 200], [$health->status, $health->httpStatus()]);
    }

    public function testDegradedStillAnswers200(): void
    {
        $health = new HealthStatus(
            Examples::version(),
            new HealthCheck('database', Status::Ok, null),
            new HealthCheck('scheduler', Status::Degraded, 'Scheduled tasks have not run recently'),
        );

        self::assertSame([Status::Degraded, 200], [$health->status, $health->httpStatus()]);
    }

    public function testDownAnswers503(): void
    {
        $health = Examples::databaseDown();

        self::assertSame([Status::Down, 503], [$health->status, $health->httpStatus()]);
    }

    public function testKeepsTheChecksInOrder(): void
    {
        $names = array_map(static fn(HealthCheck $c): string => $c->name, Examples::healthy()->checks);

        self::assertSame(['database', 'scheduler', 'storage'], $names);
    }

    public function testRejectsTwoChecksWithTheSameName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HealthStatus(
            Examples::version(),
            new HealthCheck('database', Status::Ok, null),
            new HealthCheck('database', Status::Down, 'Database unavailable'),
        );
    }
}
