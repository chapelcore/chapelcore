<?php

/**
 * DatabaseConnectionPing.php — answers the core's question "can the database answer a
 * query?" for Laravel's default connection.
 *
 * Why: the rule (unreachable → down, with a generic message) lives in the core's
 * DatabaseProbe. This adapter only runs a trivial query and turns any failure into
 * false, as the DatabasePing port promises, so an outage never becomes an exception.
 *
 * How it connects: AppServiceProvider binds DatabasePing to it; DatabaseProbe asks it.
 */

declare(strict_types=1);

namespace App\Health;

use ChapelCore\Core\Health\DatabasePing;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

final readonly class DatabaseConnectionPing implements DatabasePing
{
    public function __construct(private ConnectionResolverInterface $databases) {}

    public function canQuery(): bool
    {
        try {
            $this->databases->connection()->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
