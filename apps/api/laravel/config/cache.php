<?php

/**
 * cache.php — keeps the rate limiter's counts in the database, whatever the main cache is.
 *
 * Why: the client-error limit (10 a minute per client IP, phase-1 §6.3) must count across
 * every PHP process. Shared hosting has no Redis, and a file cache isn't safe to share
 * between processes, so the counts live in the database's cache table. Naming the store
 * here means a different CACHE_STORE (the tests use "array") can't move them.
 *
 * How it connects: Laravel merges this file with its own config/cache.php, so the stores
 * and CACHE_STORE stay Laravel's; its RateLimiter reads "limiter". The table comes from
 * database/migrations/0001_01_01_000001_create_cache_table.php.
 */

declare(strict_types=1);

return [
    'limiter' => 'database',
];
