<?php

/**
 * Doctor.php — runs the doctor's checks and collects the report.
 *
 * Why: the doctor must always finish and explain every problem it can find. A probe
 * that throws counts as that check being down, with the reason in the message (redacted,
 * because a connection error can carry credentials) and a fix; the other checks still
 * run (DOMAIN-MODEL §5.8).
 *
 * How it connects: phaseOne() builds it with the nine Phase 1 probes, in the order
 * DOMAIN-MODEL §5.8 lists them; Laravel (1.5) only supplies the values and prints the
 * DoctorReport that run() returns.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Clock;
use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Diagnostics\Redactor;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use Throwable;

final readonly class Doctor
{
    /** @var list<DoctorProbe> */
    private array $probes;

    private Redactor $redactor;

    public function __construct(DoctorProbe ...$probes)
    {
        $this->probes = array_values($probes);
        $this->redactor = new Redactor();
    }

    /** The nine Phase 1 checks, in DOMAIN-MODEL §5.8's order; the PHP checks read the running PHP. */
    public static function phaseOne(
        Environment $environment,
        DatabaseDriver $driver,
        DatabaseCharsets $charsets,
        string $storageDirectory,
        HeartbeatStore $heartbeats,
        string $appUrl,
        bool $debug,
        CertificateExpiries $certificates,
        Clock $clock,
    ): self {
        return new self(
            new PhpVersionProbe(),
            new PhpExtensionsProbe($driver),
            new PhpArgon2Probe(),
            new DatabaseCharsetProbe($driver, $charsets),
            new StorageWritableProbe($storageDirectory),
            new SchedulerHeartbeatProbe($heartbeats, $clock),
            new AppUrlProbe($environment, $appUrl),
            new AppDebugProbe($environment, $debug),
            new TlsCertificateProbe($environment, $certificates, $clock),
        );
    }

    public function run(): DoctorReport
    {
        return new DoctorReport(...array_map($this->runOne(...), $this->probes));
    }

    private function runOne(DoctorProbe $probe): DoctorCheck
    {
        try {
            return $probe->check();
        } catch (Throwable $exception) {
            $reason = $exception->getMessage() === '' ? $exception::class : $this->redactor->text($exception->getMessage());

            return new DoctorCheck(
                $probe->name(),
                Status::Down,
                "The check could not run: {$reason}",
                'Fix what the message describes, then run the doctor again.',
            );
        }
    }
}
