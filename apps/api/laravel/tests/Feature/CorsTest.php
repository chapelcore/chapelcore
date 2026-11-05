<?php

/**
 * CorsTest.php — a browser may call the API from the configured site origins only,
 * with credentials, and never from "*".
 *
 * Why: CLAUDE.md security rules (CORS): in the api.-subdomain layout the site and admin
 * call the API from another origin, so the browser needs CORS headers naming that
 * origin exactly, with credentials allowed for the Phase 3 session cookie. A wildcard
 * is never allowed, even if someone writes one in .env. Error responses carry the
 * headers too, so the admin can read the reference code of a failed call; X-Request-Id
 * and Retry-After are exposed to JavaScript for the same reason (a 202 has no body).
 * The origins are read once at boot, so this class sets them before the app starts.
 *
 * How it connects: tests config/cors.php with Laravel's built-in HandleCors middleware,
 * on every API path in both route layouts. CorsNotConfiguredTest covers the
 * same-domain layout, where no CORS headers are sent at all.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class CorsTest extends TestCase
{
    // the "*" must be ignored, not obeyed
    protected array $bootEnvironment = ['CHAPELCORE_CORS_ORIGINS' => 'https://test.libbynaz.org, https://admin.example.org,*'];

    public function test_a_preflight_from_a_configured_origin_is_allowed_with_credentials(): void
    {
        $response = $this->preflight('https://test.libbynaz.org');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'https://test.libbynaz.org');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->assertStringContainsStringIgnoringCase('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsStringIgnoringCase('Content-Type', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_every_configured_origin_is_allowed(): void
    {
        $response = $this->preflight('https://admin.example.org');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://admin.example.org');
    }

    public function test_a_request_from_a_configured_origin_gets_its_origin_back(): void
    {
        $response = $this->get('/health', ['Origin' => 'https://test.libbynaz.org']);

        $response->assertHeader('Access-Control-Allow-Origin', 'https://test.libbynaz.org');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_the_reference_code_and_retry_after_are_readable_by_the_page(): void
    {
        $response = $this->get('/health', ['Origin' => 'https://test.libbynaz.org']);

        $exposed = array_map(trim(...), explode(',', strtolower((string) $response->headers->get('Access-Control-Expose-Headers'))));
        $this->assertContains('x-request-id', $exposed);
        $this->assertContains('retry-after', $exposed);
    }

    public function test_an_error_response_carries_the_cors_headers_too(): void
    {
        $response = $this->get('/no-such-page', ['Origin' => 'https://test.libbynaz.org']);

        $response->assertNotFound();
        $response->assertHeader('Access-Control-Allow-Origin', 'https://test.libbynaz.org');
    }

    public function test_another_origin_is_not_allowed(): void
    {
        $preflight = $this->preflight('https://evil.example');
        $request = $this->get('/health', ['Origin' => 'https://evil.example']);

        $preflight->assertHeaderMissing('Access-Control-Allow-Origin');
        $request->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_a_lookalike_of_a_configured_origin_is_not_allowed(): void
    {
        foreach (['http://test.libbynaz.org', 'https://test.libbynaz.org.evil.example', 'https://test.libbynaz.org:8443'] as $origin) {
            $this->preflight($origin)->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    /** @return TestResponse<Response> */
    private function preflight(string $origin): TestResponse
    {
        return $this->call('OPTIONS', '/diagnostics/client-errors', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);
    }
}
