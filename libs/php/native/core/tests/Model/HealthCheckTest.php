<?php

/**
 * HealthCheckTest.php — tests one public health check.
 *
 * Why: the health endpoint is public, so a check's rules matter: its name is a Key,
 * and a passing check says nothing (message null when ok, DOMAIN-MODEL 0.4 §4).
 *
 * How it connects: tests src/Model/HealthCheck.php; HealthStatus holds a list of them.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\Status;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HealthCheckTest extends TestCase
{
    public function testAPassingCheckHasNoMessage(): void
    {
        $check = new HealthCheck('database', Status::Ok, null);

        self::assertSame(['database', Status::Ok, null], [$check->name, $check->status, $check->message]);
    }

    public function testAFailingCheckMayExplain(): void
    {
        self::assertSame('Database unavailable', (new HealthCheck('database', Status::Down, 'Database unavailable'))->message);
    }

    public function testAFailingCheckMayStaySilent(): void
    {
        self::assertNull((new HealthCheck('scheduler', Status::Degraded, null))->message);
    }

    public function testRejectsANameThatIsNotAKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HealthCheck('Database', Status::Ok, null);
    }

    public function testRejectsAMessageOnAPassingCheck(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HealthCheck('database', Status::Ok, 'Database available');
    }

    public function testRejectsAnEmptyMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HealthCheck('database', Status::Down, '');
    }
}
