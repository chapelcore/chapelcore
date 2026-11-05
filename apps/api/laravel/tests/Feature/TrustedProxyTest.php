<?php

/**
 * TrustedProxyTest.php — behind a trusted proxy, the rate limit is keyed on the client
 * IP the proxy forwards, not on the proxy's own address.
 *
 * Why: CLAUDE.md security rules: rate limits are keyed on the real client IP
 * (proxy-aware). Behind a proxy or CDN every request arrives from the proxy's address,
 * so without this all visitors would share one limit. X-Forwarded-For is believed only
 * when the request comes from an address in CHAPELCORE_TRUSTED_PROXIES; anyone else
 * could write any address in it. The setting is read once at boot, so this class sets
 * it before the app starts.
 *
 * How it connects: tests config/trustedproxy.php with Laravel's built-in TrustProxies
 * middleware and the client-errors limiter. ClientErrorRateLimitTest covers the default
 * (no trusted proxies).
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    protected array $bootEnvironment = ['CHAPELCORE_TRUSTED_PROXIES' => '10.0.0.1, 10.0.0.2'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useEmptyLogFolder();
        $this->freezeTime();
    }

    public function test_clients_behind_a_trusted_proxy_each_have_their_own_limit(): void
    {
        $this->sendReports(from: '10.0.0.1', forwardedFor: '192.0.2.1', count: 10);

        $this->sendReport(from: '10.0.0.1', forwardedFor: '192.0.2.2')->assertStatus(202);
    }

    public function test_a_client_behind_a_trusted_proxy_is_still_limited(): void
    {
        $this->sendReports(from: '10.0.0.1', forwardedFor: '192.0.2.1', count: 10);

        $this->sendReport(from: '10.0.0.1', forwardedFor: '192.0.2.1')->assertStatus(429);
    }

    public function test_every_listed_proxy_is_trusted(): void
    {
        $this->sendReports(from: '10.0.0.1', forwardedFor: '192.0.2.1', count: 10);

        // the same client through the second proxy: still the same client
        $this->sendReport(from: '10.0.0.2', forwardedFor: '192.0.2.1')->assertStatus(429);
    }

    public function test_a_caller_that_is_not_a_listed_proxy_cannot_choose_its_address(): void
    {
        foreach (range(1, 10) as $report) {
            $this->sendReport(from: '203.0.113.9', forwardedFor: "192.0.2.{$report}")->assertStatus(202);
        }

        $this->sendReport(from: '203.0.113.9', forwardedFor: '192.0.2.99')->assertStatus(429);
    }

    private function sendReports(string $from, string $forwardedFor, int $count): void
    {
        foreach (range(1, $count) as $report) {
            $this->sendReport($from, $forwardedFor);
        }
    }

    /** @return TestResponse<Response> */
    private function sendReport(string $from, string $forwardedFor): TestResponse
    {
        $server = ['REMOTE_ADDR' => $from, 'HTTP_X_FORWARDED_FOR' => $forwardedFor, 'CONTENT_TYPE' => 'application/json'];

        return $this->call('POST', '/diagnostics/client-errors', server: $server, content: (string) json_encode([
            'app' => 'web',
            'appVersion' => '0.1.0',
            'path' => '/events',
            'message' => 'Failed to fetch',
            'stack' => [],
            'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/144.0',
            'occurredAt' => '2026-11-04T18:00:00.000Z',
        ]));
    }
}
