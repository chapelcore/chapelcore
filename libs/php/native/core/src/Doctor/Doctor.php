<?php

/**
 * Doctor.php — runs the doctor's checks and collects the report.
 *
 * Why: the doctor must always finish and explain every problem it can find. A probe
 * that throws counts as that check being down, with the reason in the message (redacted,
 * because a connection error can carry credentials) and a fix; the other checks still
 * run (DOMAIN-MODEL §5.8).
 *
 * How it connects: Laravel (1.5) builds it with the nine Phase 1 probes, in the order
 * DOMAIN-MODEL §5.8 lists them, and prints the DoctorReport that run() returns.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Diagnostics\Redactor;
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
