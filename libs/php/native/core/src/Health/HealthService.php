<?php

/**
 * HealthService.php — builds the health report for GET {apiBase}/health.
 *
 * Why: it runs every probe, in the order given, on every call, and lets HealthStatus
 * work out the overall status. A probe that throws counts as that check being down
 * (with no message, since /health is public), so one broken probe can't turn the
 * endpoint into a 500 and the other checks still run.
 *
 * How it connects: Laravel (1.5) builds it with the version from VersionReader and
 * the database, scheduler and storage probes; the Serializer writes its result.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\HealthStatus;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Model\VersionInfo;
use Throwable;

final readonly class HealthService
{
    /** @var list<HealthProbe> */
    private array $probes;

    public function __construct(
        private VersionInfo $version,
        HealthProbe ...$probes,
    ) {
        $this->probes = array_values($probes);
    }

    public function status(): HealthStatus
    {
        return new HealthStatus($this->version, ...array_map(self::run(...), $this->probes));
    }

    private static function run(HealthProbe $probe): HealthCheck
    {
        try {
            return $probe->check();
        } catch (Throwable) {
            return new HealthCheck($probe->name(), Status::Down, null);
        }
    }
}
