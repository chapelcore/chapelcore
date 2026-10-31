<?php

/**
 * HeartbeatStore.php — the port that says when cron last ran the heartbeat.
 *
 * Why: the scheduler check needs only the time of the last heartbeat; where that time
 * is kept (a file, a database row) is the framework's business.
 *
 * How it connects: SchedulerProbe reads it; Laravel (1.5) implements it, and its
 * `chapelcore:heartbeat` command (run by cron every minute) records each beat.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

use DateTimeImmutable;

interface HeartbeatStore
{
    /** When the last heartbeat was recorded, or null if none ever was. */
    public function lastBeat(): ?DateTimeImmutable;
}
