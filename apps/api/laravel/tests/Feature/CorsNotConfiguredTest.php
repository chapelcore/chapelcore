<?php

/**
 * CorsNotConfiguredTest.php — with no CORS origins configured, the API sends no CORS
 * headers, so a browser allows same-origin calls only.
 *
 * Why: CLAUDE.md security rules (CORS): no wildcards, and in the same-domain layout
 * (example.org/api) CORS isn't needed at all. Empty is the default, so a new install is
 * closed until someone names an origin.
 *
 * How it connects: tests config/cors.php with CHAPELCORE_CORS_ORIGINS empty, as in
 * phpunit.xml and .env.example. CorsTest covers configured origins.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class CorsNotConfiguredTest extends TestCase
{
    public function test_no_origin_is_allowed(): void
    {
        $preflight = $this->call('OPTIONS', '/diagnostics/client-errors', server: [
            'HTTP_ORIGIN' => 'https://test.libbynaz.org',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $request = $this->get('/health', ['Origin' => 'https://test.libbynaz.org']);

        $preflight->assertHeaderMissing('Access-Control-Allow-Origin');
        $request->assertHeaderMissing('Access-Control-Allow-Origin');
        $request->assertHeaderMissing('Access-Control-Allow-Credentials');
    }
}
