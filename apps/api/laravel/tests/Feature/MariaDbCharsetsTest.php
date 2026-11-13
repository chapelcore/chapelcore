<?php

/**
 * MariaDbCharsetsTest.php — App\Doctor\MariaDbCharsets reads the character sets of the
 * database and of each of its tables from MariaDB.
 *
 * Why: the doctor's database.charset check (DOMAIN-MODEL §5.8) needs them, because the
 * host creates databases in latin1 by default and latin1 can't store every character
 * (CLAUDE.md security rules: utf8mb4 everywhere). The connection being utf8mb4 isn't
 * enough: a database or table made in latin1 stays latin1.
 *
 * How it connects: tests the adapter for the core's DatabaseCharsets port against the
 * real MariaDB. The core's DatabaseCharsetProbe decides what the answers mean.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Doctor\MariaDbCharsets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MariaDbCharsetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_the_database_character_set(): void
    {
        $this->assertSame('utf8mb4', $this->charsets()->database());
    }

    public function test_it_reads_each_table_character_set_by_name(): void
    {
        $this->assertSame([
            'cache' => 'utf8mb4',
            'cache_locks' => 'utf8mb4',
            'migrations' => 'utf8mb4',
        ], $this->charsets()->tables());
    }

    public function test_a_table_made_in_latin1_is_reported_as_latin1(): void
    {
        DB::statement('create table doctor_latin1_example (id int) character set latin1');
        try {
            $tables = $this->charsets()->tables();
        } finally {
            DB::statement('drop table doctor_latin1_example');
        }

        $this->assertSame('latin1', $tables['doctor_latin1_example'] ?? null);
    }

    public function test_views_are_not_tables(): void
    {
        DB::statement('create view doctor_example_view as select 1 as one');
        try {
            $tables = $this->charsets()->tables();
        } finally {
            DB::statement('drop view doctor_example_view');
        }

        $this->assertArrayNotHasKey('doctor_example_view', $tables);
    }

    private function charsets(): MariaDbCharsets
    {
        return $this->app->make(MariaDbCharsets::class);
    }
}
