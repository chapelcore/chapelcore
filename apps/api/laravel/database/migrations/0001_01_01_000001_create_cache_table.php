<?php

/**
 * 0001_01_01_000001_create_cache_table.php — the app's first migration: Laravel's cache
 * and cache-lock tables.
 *
 * Why: the rate limiter keeps its counts in the database cache store (config/cache.php),
 * because shared hosting has no Redis (phase-1 §6.3). This is Laravel's own migration
 * (`php artisan make:cache-table`), unchanged apart from this header and the formatting.
 * The tables get utf8mb4 from the connection (config/database.php).
 *
 * How it connects: run by `php artisan migrate` (the deploy, 1.9) and by RefreshDatabase
 * in the tests; MigrationTest checks it.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
