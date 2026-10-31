<?php

/**
 * DoctorReportTest.php — tests the doctor's overall result.
 *
 * Why: `chapelcore:doctor` runs in every deploy, before migrations (phase-1.md §10).
 * The report's status is the worst of its checks. Only `down` stops a deploy (exit
 * code 1): a `degraded` check, such as no scheduler heartbeat yet on a first deploy or
 * a certificate with 20 days left, is reported but must not block the release.
 *
 * How it connects: tests src/Doctor/DoctorReport.php; Doctor::run() returns one, and
 * the Laravel command (1.5) prints it and exits with exitCode().
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\DoctorReport;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctorReportTest extends TestCase
{
    /** @return iterable<string, array{list<Status>, Status, int}> */
    public static function outcomes(): iterable
    {
        yield 'all ok' => [[Status::Ok, Status::Ok], Status::Ok, 0];
        yield 'degraded passes' => [[Status::Ok, Status::Degraded], Status::Degraded, 0];
        yield 'down fails' => [[Status::Degraded, Status::Down, Status::Ok], Status::Down, 1];
        yield 'no checks' => [[], Status::Ok, 0];
    }

    /** @param list<Status> $statuses */
    #[DataProvider('outcomes')]
    public function testTheWorstCheckDecidesAndOnlyDownFails(array $statuses, Status $status, int $exitCode): void
    {
        $checks = array_map(
            static fn(int $i, Status $s): DoctorCheck => new DoctorCheck("check_{$i}", $s, null, $s === Status::Ok ? null : 'Fix it.'),
            array_keys($statuses),
            $statuses,
        );

        $report = new DoctorReport(...$checks);

        self::assertSame([$status, $exitCode], [$report->status, $report->exitCode()]);
        self::assertSame($checks, $report->checks);
    }

    public function testRejectsTwoChecksWithTheSameName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DoctorReport(
            new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null),
            new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null),
        );
    }
}
