<?php

/**
 * MigrationTest.php — the migrations create the app's tables, all in utf8mb4, and can be
 * rolled back.
 *
 * Why: CLAUDE.md security rules: utf8mb4 everywhere, because the host's default is
 * latin1. The connection is already utf8mb4 (DatabaseConnectionTest); this checks the
 * tables themselves. The first migration is Laravel's cache table, which holds the rate
 * limiter's counts (phase-1 §6.3). The deploy (1.9) runs `php artisan migrate`, so a
 * migration that only works one way would block a rollback.
 *
 * How it connects: tests database/migrations against the real MariaDB.
 * RefreshDatabase migrates the empty test database before the first test. The doctor's
 * database.charset check (checkpoint 5) reads the same information at run time.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cache_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
    }

    public function test_every_table_and_text_column_is_utf8mb4(): void
    {
        $tables = DB::select(
            'select table_name as name, table_collation as collation from information_schema.tables where table_schema = database()',
        );
        $columns = DB::select(
            'select table_name as name, column_name as col, collation_name as collation from information_schema.columns'
            .' where table_schema = database() and collation_name is not null',
        );

        $this->assertNotEmpty($tables);
        foreach ([...$tables, ...$columns] as $row) {
            $row = (array) $row;
            $this->assertSame('utf8mb4_unicode_ci', $row['collation'] ?? null, json_encode($row) ?: '');
        }
    }

    public function test_the_migrations_roll_back_and_run_again(): void
    {
        [$down] = $this->runCommand('migrate:rollback --force');
        $this->assertSame(0, $down);
        $this->assertFalse(Schema::hasTable('cache'));

        [$up] = $this->runCommand('migrate --force');
        $this->assertSame(0, $up);
        $this->assertTrue(Schema::hasTable('cache'));
    }
}
