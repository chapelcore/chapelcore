<?php

/**
 * chapelcore.php — ChapelCore's own settings, as opposed to Laravel's.
 *
 * Why: values that differ between installs come from .env (CLAUDE.md), and each one is
 * read here once, so the rest of the app asks config('chapelcore.…'), never env(). The
 * same build serves both route layouts (PLAN D16) and finds version.json wherever the
 * deploy puts it.
 *
 * How it connects: bootstrap/app.php reads api_prefix when it loads the routes;
 * AppServiceProvider reads version_path and heartbeat_path for /health and the
 * heartbeat command. .env.example lists the variables.
 */

declare(strict_types=1);

return [
    // "" serves api.example.org/health; "api" serves example.org/api/health
    'api_prefix' => env('CHAPELCORE_API_PREFIX', ''),

    // the only place the version is written (CLAUDE.md "Versioning"); by default the
    // repository root, three folders above this app
    'version_path' => env('CHAPELCORE_VERSION_PATH', base_path('../../../version.json')),

    // written by `php artisan chapelcore:heartbeat` (cron, every minute); in storage/, so
    // it survives deploys (shared/storage) and a database outage (E1)
    'heartbeat_path' => storage_path('framework/chapelcore-heartbeat'),
];
