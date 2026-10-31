<?php

/**
 * DatabaseProbeTest.php — tests the `database` health check.
 *
 * Why: if the database can't answer a trivial query, nothing works, so /health says
 * down (503) and a deploy rolls back. The message stays generic because /health is
 * public (DOMAIN-MODEL 0.4 §4).
 *
 * How it connects: tests src/Health/DatabaseProbe.php with a stand-in DatabasePing;
 * Laravel (1.5) implements the real ping against MariaDB or PostgreSQL.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Health;

use ChapelCore\Core\Health\DatabasePing;
use ChapelCore\Core\Health\DatabaseProbe;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\TestCase;

final class DatabaseProbeTest extends TestCase
{
    public function testAnAnsweringDatabaseIsOk(): void
    {
        $check = (new DatabaseProbe(self::ping(true)))->check();

        self::assertSame(['database', Status::Ok, null], [$check->name, $check->status, $check->message]);
    }

    public function testAnUnreachableDatabaseIsDown(): void
    {
        $check = (new DatabaseProbe(self::ping(false)))->check();

        self::assertSame([Status::Down, 'Database unavailable'], [$check->status, $check->message]);
    }

    public function testIsNamedDatabase(): void
    {
        self::assertSame('database', (new DatabaseProbe(self::ping(true)))->name());
    }

    private static function ping(bool $answers): DatabasePing
    {
        return new class ($answers) implements DatabasePing {
            public function __construct(private readonly bool $answers) {}

            public function canQuery(): bool
            {
                return $this->answers;
            }
        };
    }
}
