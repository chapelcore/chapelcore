<?php

/**
 * trustedproxy.php — the proxies whose X-Forwarded-For header is believed, from
 * CHAPELCORE_TRUSTED_PROXIES (comma-separated addresses or ranges such as 10.0.0.0/8).
 *
 * Why: CLAUDE.md security rules: rate limits are keyed on the real client IP. Behind a
 * proxy or CDN, every request arrives from the proxy, and the visitor's address is in
 * X-Forwarded-For. Anyone can send that header, so it is believed only from the proxies
 * listed here. Empty (the default, and right for Namecheap) means no proxy is trusted.
 *
 * How it connects: Laravel's built-in TrustProxies middleware reads "proxies" when the
 * app itself names none; $request->ip() then gives the client's address to the
 * client-errors rate limiter. .env.example documents the variable.
 */

declare(strict_types=1);

$proxies = array_map(trim(...), explode(',', (string) env('CHAPELCORE_TRUSTED_PROXIES', '')));
$proxies = array_values(array_filter($proxies, static fn (string $proxy): bool => $proxy !== ''));

return [
    'proxies' => $proxies === [] ? null : $proxies,
];
