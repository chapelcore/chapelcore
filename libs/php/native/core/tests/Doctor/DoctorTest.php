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
 * probes are tested one by one. Doctor::phaseOne() builds all nine in DOMAIN-MODEL's
 * order, so Laravel (1.5) only supplies the values.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Doctor\CertificateExpiries;
use ChapelCore\Core\Doctor\DatabaseCharsets;
use ChapelCore\Core\Doctor\Doctor;
use ChapelCore\Core\Doctor\DoctorProbe;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use DateTimeImmutable;
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

    public function testPhaseOneRunsTheNineChecksInTheModelsOrder(): void
    {
        $charsets = new class implements DatabaseCharsets {
            public function database(): string
            {
                return 'utf8mb4';
            }

            public function tables(): array
            {
                return [];
            }
        };
        $heartbeats = new class implements HeartbeatStore {
            public function lastBeat(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T11:59:00Z');
            }
        };
        $certificates = new class implements CertificateExpiries {
            public function expiries(): array
            {
                return ['libbynaz.org' => new DateTimeImmutable('2026-12-20T12:00:00Z')];
            }
        };

        $report = Doctor::phaseOne(
            environment: Environment::Production,
            driver: DatabaseDriver::MariaDb,
            charsets: $charsets,
            storageDirectory: sys_get_temp_dir(),
            heartbeats: $heartbeats,
            appUrl: 'https://libbynaz.org',
            debug: false,
            certificates: $certificates,
            clock: new FixedClock(),
        )->run();

        self::assertSame(
            [
                'php.version', 'php.extensions', 'php.argon2', 'database.charset', 'storage.writable',
                'scheduler.heartbeat', 'app.url', 'app.debug', 'tls.certificate',
            ],
            array_map(static fn(DoctorCheck $c): string => $c->name, $report->checks),
        );
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
