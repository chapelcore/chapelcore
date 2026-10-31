<?php

/**
 * TlsCertificateProbe.php — the doctor's `tls.certificate` check.
 *
 * Why: certificates renew automatically through acme.sh, and nobody gets an email when
 * renewal stops (hosting runbook §7; plan risk R10). With 30 days or more left a
 * certificate is ok; under 30 it is degraded, a warning in good time; under 7, expired
 * or unreadable it is down (DOMAIN-MODEL §5.8). Every host is reported and the worst
 * decides. Not checked in dev.
 *
 * How it connects: one of the doctor's probes; it reads the CertificateExpiries port and
 * the Clock.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Clock;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;

final readonly class TlsCertificateProbe implements DoctorProbe
{
    private const int DAY = 86400;

    private const int WARN_DAYS = 30;

    private const int DOWN_DAYS = 7;

    public function __construct(
        private Environment $environment,
        private CertificateExpiries $certificates,
        private Clock $clock,
    ) {}

    public function name(): string
    {
        return 'tls.certificate';
    }

    /** @throws LogicException when the port names no hosts at all */
    public function check(): DoctorCheck
    {
        if ($this->environment === Environment::Dev) {
            return new DoctorCheck($this->name(), Status::Ok, "Not checked in {$this->environment->value}", null);
        }

        $expiries = $this->certificates->expiries();
        if ($expiries === []) {
            throw new LogicException('No hosts were given to check');
        }

        $statuses = [];
        $messages = [];
        foreach ($expiries as $host => $expiresAt) {
            [$statuses[], $messages[]] = $this->judge($host, $expiresAt);
        }
        $status = Status::worst(...$statuses);

        return new DoctorCheck(
            $this->name(),
            $status,
            implode('; ', $messages),
            $status === Status::Ok ? null : 'Check the acme.sh cron line; see the hosting runbook §7.',
        );
    }

    /** @return array{Status, string} */
    private function judge(string $host, ?DateTimeImmutable $expiresAt): array
    {
        if ($expiresAt === null) {
            return [Status::Down, "Could not read the certificate for {$host}"];
        }

        $date = $expiresAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $secondsLeft = $expiresAt->getTimestamp() - $this->clock->now()->getTimestamp();
        if ($secondsLeft <= 0) {
            return [Status::Down, "{$host} expired on {$date}"];
        }

        $days = intdiv($secondsLeft, self::DAY);
        $status = match (true) {
            $secondsLeft >= self::WARN_DAYS * self::DAY => Status::Ok,
            $secondsLeft >= self::DOWN_DAYS * self::DAY => Status::Degraded,
            default => Status::Down,
        };
        $left = match ($days) {
            0 => 'less than a day',
            1 => '1 day',
            default => "{$days} days",
        };

        return [$status, "{$host} expires on {$date} (in {$left})"];
    }
}
