<?php

/**
 * DoctorReport.php — the doctor's checks and their overall result.
 *
 * Why: `chapelcore:doctor` runs in every deploy, before migrations (phase-1.md §10).
 * The status is the worst of the checks, and only `down` stops the deploy (exit code
 * 1). A `degraded` check, such as no scheduler heartbeat yet on a first deploy, is
 * reported but doesn't block the release.
 *
 * How it connects: Doctor::run() returns one; the Laravel command (1.5) prints the
 * checks and exits with exitCode().
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use InvalidArgumentException;

final readonly class DoctorReport
{
    /** @var list<DoctorCheck> */
    public array $checks;

    public Status $status;

    /** @throws InvalidArgumentException when two checks share a name */
    public function __construct(DoctorCheck ...$checks)
    {
        $names = array_map(static fn(DoctorCheck $check): string => $check->name, $checks);
        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException('DoctorReport.checks must have unique names');
        }

        $this->checks = array_values($checks);
        $this->status = Status::worst(...array_map(static fn(DoctorCheck $check): Status => $check->status, $this->checks));
    }

    /** 1 when any check is down, so a deploy stops before migrating; otherwise 0. */
    public function exitCode(): int
    {
        return $this->status === Status::Down ? 1 : 0;
    }
}
