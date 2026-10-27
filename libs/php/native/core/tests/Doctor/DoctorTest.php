<?php

/**
 * DoctorTest.php — tests how the doctor runs its checks.
 *
 * Why: the doctor must always finish and explain every problem it can find. A probe
 * that throws (say, the database can't be reached to read its character set) counts as
 * that check being down, with the reason in the message (redacted, since a connection
 * error can include credentials) and a fix, and the remaining checks still run.
 *
 * How it connects: tests src/Doctor/Doctor.php with stand-in probes; the real Phase 1
 * probes are tested one by one, and Laravel (1.5) builds the doctor with all nine.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\Doctor;
use ChapelCore\Core\Doctor\DoctorProbe;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DoctorTest extends TestCase
{
    public function testRunsEveryProbeInOrder(): void
    {
        $doctor = new Doctor(
            self::probe(new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null)),
            self::probe(new DoctorCheck('php.argon2', Status::Degraded, 'Argon2id is not available', 'Ask your host.')),
        );

        $report = $doctor->run();

        self::assertSame(['php.version', 'php.argon2'], array_map(static fn(DoctorCheck $c): string => $c->name, $report->checks));
        self::assertSame(Status::Degraded, $report->status);
    }

    public function testAProbeThatThrowsIsDownWithItsRedactedReasonAndTheOthersStillRun(): void
    {
        $failing = new class implements DoctorProbe {
            public function name(): string
            {
                return 'database.charset';
            }

            public function check(): DoctorCheck
            {
                throw new RuntimeException("SQLSTATE[HY000] [1045] Access denied for user 'chapel'@'localhost' (using password: YES)");
            }
        };
        $doctor = new Doctor($failing, self::probe(new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null)));

        $report = $doctor->run();

        $failed = $report->checks[0];
        self::assertSame(['database.charset', Status::Down], [$failed->name, $failed->status]);
        self::assertStringStartsWith('The check could not run: ', (string) $failed->message);
        self::assertStringContainsString("Access denied for user 'chapel'@'localhost'", (string) $failed->message);
        self::assertSame('Fix what the message describes, then run the doctor again.', $failed->fix);
        self::assertSame(['php.version', Status::Ok], [$report->checks[1]->name, $report->checks[1]->status]);
    }

    public function testAThrownMessageIsRedacted(): void
    {
        $failing = new class implements DoctorProbe {
            public function name(): string
            {
                return 'tls.certificate';
            }

            public function check(): DoctorCheck
            {
                throw new RuntimeException('GET https://api.example.org/check?token=abc123 failed for ann@example.org');
            }
        };

        $message = (string) (new Doctor($failing))->run()->checks[0]->message;

        self::assertSame('The check could not run: GET https://api.example.org/check?token=[redacted] failed for [email]', $message);
    }

    private static function probe(DoctorCheck $result): DoctorProbe
    {
        return new readonly class ($result) implements DoctorProbe {
            public function __construct(private DoctorCheck $result) {}

            public function name(): string
            {
                return $this->result->name;
            }

            public function check(): DoctorCheck
            {
                return $this->result;
            }
        };
    }
}
