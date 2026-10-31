<?php

/**
 * HealthProbe.php — the port for one public health check.
 *
 * Why: each check (database, scheduler, storage) is its own small class, and the
 * framework decides which ones to run. HealthService only knows this interface.
 *
 * How it connects: DatabaseProbe, SchedulerProbe and StorageProbe implement it;
 * HealthService runs them; Laravel (1.5) chooses them.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

use ChapelCore\Core\Model\HealthCheck;

interface HealthProbe
{
    /** The check's name (a Key), also used if check() throws. */
    public function name(): string;

    public function check(): HealthCheck;
}
