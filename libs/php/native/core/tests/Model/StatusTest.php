<?php

/**
 * StatusTest.php — tests the ok / degraded / down status and its "worst wins" rule.
 *
 * Why: the overall health and doctor status is the worst of the checks (DOMAIN-MODEL §4).
 * The deploy pipeline reads that result, so the ordering has to be exact.
 *
 * How it connects: tests src/Model/Status.php; HealthStatus uses Status::worst().
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    /** @return iterable<string, array{list<Status>, Status}> */
    public static function worstCases(): iterable
    {
        yield 'nothing to check' => [[], Status::Ok];
        yield 'all ok' => [[Status::Ok, Status::Ok], Status::Ok];
        yield 'one degraded' => [[Status::Ok, Status::Degraded, Status::Ok], Status::Degraded];
        yield 'down beats degraded' => [[Status::Degraded, Status::Down, Status::Ok], Status::Down];
        yield 'order does not matter' => [[Status::Down, Status::Degraded], Status::Down];
    }

    public function testValuesAreTheModelEnum(): void
    {
        self::assertSame(['ok', 'degraded', 'down'], array_map(static fn(Status $s): string => $s->value, Status::cases()));
    }

    /** @param list<Status> $statuses */
    #[DataProvider('worstCases')]
    public function testWorstWins(array $statuses, Status $expected): void
    {
        self::assertSame($expected, Status::worst(...$statuses));
    }
}
