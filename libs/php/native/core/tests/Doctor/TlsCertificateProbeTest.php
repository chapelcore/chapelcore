<?php

/**
 * TlsCertificateProbeTest.php — tests the doctor's `tls.certificate` check.
 *
 * Why: certificates renew automatically through acme.sh, and nobody gets an email when
 * renewal stops (hosting runbook §7; plan risk R10). The doctor warns 30 days ahead
 * (degraded) and is down with 7 days left, once expired, or when a certificate can't be
 * read (DOMAIN-MODEL §5.8). An API on its own host has its own certificate, so every
 * host is checked and the worst decides. Not checked in dev.
 *
 * How it connects: tests src/Doctor/TlsCertificateProbe.php with a stand-in for the
 * CertificateExpiries port, which Laravel (1.5) answers for the site and API hosts, and
 * a FixedClock (2026-10-25T12:00:00Z).
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\CertificateExpiries;
use ChapelCore\Core\Doctor\TlsCertificateProbe;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TlsCertificateProbeTest extends TestCase
{
    /** @return iterable<string, array{?string, Status, string}> */
    public static function expiries(): iterable
    {
        yield 'eight weeks left' => ['2026-12-20T12:00:00Z', Status::Ok, 'libbynaz.org expires on 2026-12-20 (in 56 days)'];
        yield 'exactly 30 days left' => ['2026-11-24T12:00:00Z', Status::Ok, 'libbynaz.org expires on 2026-11-24 (in 30 days)'];
        yield 'a second under 30 days' => ['2026-11-24T11:59:59Z', Status::Degraded, 'libbynaz.org expires on 2026-11-24 (in 29 days)'];
        yield 'exactly 7 days left' => ['2026-11-01T12:00:00Z', Status::Degraded, 'libbynaz.org expires on 2026-11-01 (in 7 days)'];
        yield 'a second under 7 days' => ['2026-11-01T11:59:59Z', Status::Down, 'libbynaz.org expires on 2026-11-01 (in 6 days)'];
        yield 'one day left' => ['2026-10-26T12:00:00Z', Status::Down, 'libbynaz.org expires on 2026-10-26 (in 1 day)'];
        yield 'hours left' => ['2026-10-26T00:00:00Z', Status::Down, 'libbynaz.org expires on 2026-10-26 (in less than a day)'];
        yield 'expired' => ['2026-10-24T12:00:00Z', Status::Down, 'libbynaz.org expired on 2026-10-24'];
        yield 'unreadable' => [null, Status::Down, 'Could not read the certificate for libbynaz.org'];
    }

    #[DataProvider('expiries')]
    public function testTheTimeLeftDecides(?string $expiresAt, Status $status, string $message): void
    {
        $check = (new TlsCertificateProbe(Environment::Production, self::certificates(['libbynaz.org' => $expiresAt]), new FixedClock()))->check();

        self::assertSame(['tls.certificate', $status, $message], [$check->name, $check->status, $check->message]);
        self::assertSame($status === Status::Ok ? null : 'Check the acme.sh cron line; see the hosting runbook §7.', $check->fix);
    }

    public function testEveryHostIsReportedAndTheWorstDecides(): void
    {
        $certificates = self::certificates([
            'libbynaz.org' => '2026-12-20T12:00:00Z',
            'api.libbynaz.org' => '2026-11-10T12:00:00Z',
        ]);

        $check = (new TlsCertificateProbe(Environment::Test, $certificates, new FixedClock()))->check();

        self::assertSame(
            [Status::Degraded, 'libbynaz.org expires on 2026-12-20 (in 56 days); api.libbynaz.org expires on 2026-11-10 (in 16 days)'],
            [$check->status, $check->message],
        );
    }

    public function testIsNotCheckedInDev(): void
    {
        $check = (new TlsCertificateProbe(Environment::Dev, self::certificates(['localhost' => null]), new FixedClock()))->check();

        self::assertSame([Status::Ok, 'Not checked in dev', null], [$check->status, $check->message, $check->fix]);
    }

    public function testNoHostsToCheckIsAnError(): void
    {
        $this->expectException(LogicException::class);
        (new TlsCertificateProbe(Environment::Production, self::certificates([]), new FixedClock()))->check();
    }

    /** @param array<string, ?string> $expiries */
    private static function certificates(array $expiries): CertificateExpiries
    {
        $dates = array_map(static fn(?string $at): ?DateTimeImmutable => $at === null ? null : new DateTimeImmutable($at), $expiries);

        return new readonly class ($dates) implements CertificateExpiries {
            /** @param array<string, ?DateTimeImmutable> $dates */
            public function __construct(private array $dates) {}

            public function expiries(): array
            {
                return $this->dates;
            }
        };
    }
}
