<?php

/**
 * DatabaseProbe.php — the `database` health check (DOMAIN-MODEL 0.4 §4).
 *
 * Why: if the database can't answer a trivial query, nothing works, so the check is
 * down and /health answers 503. The message is generic because /health is public.
 *
 * How it connects: asks a DatabasePing (implemented by Laravel); HealthService runs it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\Status;

final readonly class DatabaseProbe implements HealthProbe
{
    public const string NAME = 'database';

    public function __construct(private DatabasePing $ping) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function check(): HealthCheck
    {
        return $this->ping->canQuery()
            ? new HealthCheck(self::NAME, Status::Ok, null)
            : new HealthCheck(self::NAME, Status::Down, 'Database unavailable');
    }
}
