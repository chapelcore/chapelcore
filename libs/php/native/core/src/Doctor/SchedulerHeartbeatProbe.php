<?php

/**
 * SchedulerHeartbeatProbe.php — the doctor's `scheduler.heartbeat` check.
 *
 * Why: the same rule as the public `scheduler` health check, a heartbeat in the last 5
 * minutes (DOMAIN-MODEL §5.8), but the doctor says when the last one was and points to
 * the cron line that is usually the problem. No heartbeat yet (a first deploy) is
 * degraded, so it never blocks a deploy.
 *
 * How it connects: one of the doctor's probes; it asks the health check's
 * SchedulerProbe for the status, so the two can never disagree.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Clock;
use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Health\SchedulerProbe;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Transport\Timestamp;

final readonly class SchedulerHeartbeatProbe implements DoctorProbe
{
    private const string FIX = 'Add the cron line from the hosting runbook §8.';

    public function __construct(
        private HeartbeatStore $heartbeats,
        private Clock $clock,
    ) {}

    public function name(): string
    {
        return 'scheduler.heartbeat';
    }

    public function check(): DoctorCheck
    {
        $lastBeat = $this->heartbeats->lastBeat();
        if ($lastBeat === null) {
            return new DoctorCheck($this->name(), Status::Degraded, 'No heartbeat has been recorded yet', self::FIX);
        }

        $at = 'Last heartbeat at ' . Timestamp::format($lastBeat);
        $status = (new SchedulerProbe($this->heartbeats, $this->clock))->check()->status;

        return $status === Status::Ok
            ? new DoctorCheck($this->name(), Status::Ok, $at, null)
            : new DoctorCheck($this->name(), $status, "{$at}, more than 5 minutes ago", self::FIX);
    }
}
