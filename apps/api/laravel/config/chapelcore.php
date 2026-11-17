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
 * heartbeat command, and env and tls_hosts for the doctor (chapelcore:doctor).
 * .env.example lists the variables.
 */

declare(strict_types=1);

$tlsHosts = array_map(trim(...), explode(',', (string) env('CHAPELCORE_TLS_HOSTS', '')));
$tlsHosts = array_values(array_filter($tlsHosts, static fn (string $host): bool => $host !== ''));

return [
    // "" serves api.example.org/health; "api" serves example.org/api/health
    'api_prefix' => env('CHAPELCORE_API_PREFIX', ''),

    // the only place the version is written (CLAUDE.md "Versioning"); by default the
    // repository root, three folders above this app
    'version_path' => env('CHAPELCORE_VERSION_PATH', base_path('../../../version.json')),

    // written by `php artisan chapelcore:heartbeat` (cron, every minute); in storage/, so
    // it survives deploys (shared/storage) and a database outage (E1)
    'heartbeat_path' => storage_path('framework/chapelcore-heartbeat'),

    // which environment this is, for the doctor: dev, test or production (E2); Laravel's
    // own APP_ENV stays Laravel's
    'env' => env('CHAPELCORE_ENV', 'dev'),

    // the hosts whose certificates the doctor checks, as host or host:port (port 443 when
    // left out); empty in dev, where the check doesn't run
    'tls_hosts' => $tlsHosts,
];
