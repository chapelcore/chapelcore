<?php

/**
 * DatabaseConnectionTest.php — the MariaDB connection always talks utf8mb4.
 *
 * Why: CLAUDE.md security rules: utf8mb4 everywhere, because the host's default is
 * latin1, which would mangle names and emoji. The charset is fixed in
 * config/database.php rather than read from DB_CHARSET, so this class boots with
 * latin1 in the environment to prove it can't be changed.
 *
 * How it connects: tests config/database.php against the real MariaDB. Table
 * character sets are checked by the doctor (checkpoint 5) once tables exist.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    protected array $bootEnvironment = ['DB_CHARSET' => 'latin1', 'DB_COLLATION' => 'latin1_swedish_ci'];

    public function test_the_connection_is_utf8mb4_whatever_the_environment_says(): void
    {
        $session = (array) DB::selectOne(
            'select @@character_set_client as client, @@character_set_connection as connection,'
            .' @@character_set_results as results, @@collation_connection as collation',
        );

        $this->assertSame(
            ['client' => 'utf8mb4', 'connection' => 'utf8mb4', 'results' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
            $session,
        );
    }
}
