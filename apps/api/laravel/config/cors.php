<?php

/**
 * cors.php — which browser origins may call the API: exactly the ones in
 * CHAPELCORE_CORS_ORIGINS, with credentials, never "*".
 *
 * Why: CLAUDE.md security rules (CORS). In the api.-subdomain layout the site and admin
 * run on another origin, so the browser needs these headers; in the same-domain layout
 * (example.org/api) the list stays empty and no CORS headers are sent. Credentials are
 * allowed for the Phase 3 session cookie, which is why a wildcard is never accepted:
 * a "*" in the list is dropped. The page may read X-Request-Id (a 202 has no body to
 * carry the reference code) and Retry-After.
 *
 * How it connects: read on every request by Laravel's built-in HandleCors middleware,
 * which answers preflight requests itself. "*" in paths means every API path, in both
 * route layouts. .env.example documents the variable.
 */

declare(strict_types=1);

$origins = array_map(trim(...), explode(',', (string) env('CHAPELCORE_CORS_ORIGINS', '')));

return [
    'paths' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_origins' => array_values(array_filter($origins, static fn (string $origin): bool => $origin !== '' && $origin !== '*')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-CSRF-TOKEN'],
    'exposed_headers' => ['X-Request-Id', 'Retry-After'],
    // how long a browser may reuse a preflight answer, in seconds
    'max_age' => 600,
    'supports_credentials' => true,
];
