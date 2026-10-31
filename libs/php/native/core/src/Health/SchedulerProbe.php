<?php

/**
 * SchedulerProbe.php — the `scheduler` health check (DOMAIN-MODEL 0.4 §4).
 *
 * Why: cron runs the heartbeat every minute. A heartbeat at most 5 minutes old is ok;
 * an older one, or none at all, means scheduled work has stopped, so the check is
 * degraded (the site still works, so this isn't down). A heartbeat slightly in the
 * future (cron's clock a little ahead of the web server's) counts as fresh.
 *
 * How it connects: reads a HeartbeatStore and a Clock (both supplied by Laravel);
 * HealthService runs it. The doctor's scheduler.heartbeat check (checkpoint 5) uses
 * the same rule.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

use ChapelCore\Core\Clock;
use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\Status;

final readonly class SchedulerProbe implements HealthProbe
{
    public const string NAME = 'scheduler';

    public const int MAX_AGE_SECONDS = 300;

    public function __construct(
        private HeartbeatStore $heartbeats,
        private Clock $clock,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function check(): HealthCheck
    {
        $lastBeat = $this->heartbeats->lastBeat();
        $fresh = $lastBeat !== null
            && $this->clock->now()->getTimestamp() - $lastBeat->getTimestamp() <= self::MAX_AGE_SECONDS;

        return $fresh
            ? new HealthCheck(self::NAME, Status::Ok, null)
            : new HealthCheck(self::NAME, Status::Degraded, 'Scheduled tasks have not run recently');
    }
}
