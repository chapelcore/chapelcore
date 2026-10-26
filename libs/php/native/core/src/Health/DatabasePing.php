<?php

/**
 * DatabasePing.php — the port the database check asks "can you answer a query?".
 *
 * Why: the core has no database connection of its own; the framework owns it. This
 * keeps the rule (unreachable → down) in the core and the connection in Laravel.
 *
 * How it connects: DatabaseProbe uses it; Laravel (1.5) implements it with a trivial
 * query against MariaDB or PostgreSQL.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

interface DatabasePing
{
    /** True when a trivial query succeeds; false (never an exception) when it doesn't. */
    public function canQuery(): bool;
}
