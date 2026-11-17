<?php

/**
 * ClientErrorRateLimitTest.php — each client IP may send 10 error reports a minute; the
 * 11th gets 429 RATE_LIMITED with Retry-After.
 *
 * Why: DOMAIN-MODEL §5.8 and CLAUDE.md security rules: every public write endpoint is
 * rate-limited, keyed on the real client IP. A broken page in a loop, or someone
 * hammering the endpoint, must not fill the log. The counts live in the database cache
 * table, because shared hosting has no Redis and a file cache isn't shared safely
 * between PHP processes. Rejected reports count too, so junk can't get around the limit.
 *
 * How it connects: tests the client-errors limiter in AppServiceProvider, the throttle
 * middleware on the route, the 429 rendering in RenderAsErrorEnvelope and the cache
 * limiter setting in config/cache.php. Time is frozen so Retry-After is exact.
 * TrustedProxyTest covers clients behind a proxy.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ClientErrorRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useEmptyLogFolder();
        $this->freezeTime();
    }

    public function test_ten_reports_a_minute_are_accepted(): void
    {
        foreach (range(1, 10) as $report) {
            $this->sendReport('203.0.113.9')->assertStatus(202);
        }
        $this->assertCount(10, $this->logEntries());
    }

    public function test_the_eleventh_report_in_a_minute_is_rate_limited(): void
    {
        $this->sendReports('203.0.113.9', 10);

        $response = $this->sendReport('203.0.113.9');

        $response->assertStatus(429);
        $this->assertMatchesContract((string) $response->getContent(), 'error-envelope');
        $this->assertSame('RATE_LIMITED', $response->json('error.code'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('meta.requestId'));
        $response->assertHeader('Retry-After', '60');
        $this->assertCount(10, $this->logEntries());
    }

    public function test_rejected_reports_count_toward_the_limit(): void
    {
        foreach (range(1, 10) as $report) {
            $this->sendReport('203.0.113.9', 'not json')->assertStatus(422);
        }

        $this->sendReport('203.0.113.9')->assertStatus(429);
    }

    public function test_another_client_has_its_own_limit(): void
    {
        $this->sendReports('203.0.113.9', 10);

        $this->sendReport('198.51.100.7')->assertStatus(202);
    }

    public function test_the_limit_lifts_after_a_minute(): void
    {
        $this->sendReports('203.0.113.9', 10);
        $this->sendReport('203.0.113.9')->assertStatus(429);

        $this->travel(60)->seconds();

        $this->sendReport('203.0.113.9')->assertStatus(202);
    }

    public function test_a_forwarded_for_header_from_an_untrusted_caller_is_ignored(): void
    {
        // with no trusted proxies (the default), X-Forwarded-For is just a header anyone can send
        foreach (range(1, 10) as $report) {
            $this->sendReport('203.0.113.9', forwardedFor: "192.0.2.{$report}")->assertStatus(202);
        }

        $this->sendReport('203.0.113.9', forwardedFor: '192.0.2.99')->assertStatus(429);
    }

    public function test_the_counts_are_kept_in_the_database_cache_table(): void
    {
        // phpunit.xml sets CACHE_STORE=array; the limiter must use the database anyway
        $this->sendReport('203.0.113.9');

        $this->assertGreaterThan(0, DB::table('cache')->count());
    }

    private function sendReports(string $ip, int $count): void
    {
        foreach (range(1, $count) as $report) {
            $this->sendReport($ip);
        }
    }

    /** @return TestResponse<Response> */
    private function sendReport(string $ip, ?string $body = null, ?string $forwardedFor = null): TestResponse
    {
        $server = ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json'];
        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }

        return $this->call('POST', '/diagnostics/client-errors', server: $server, content: $body ?? self::report());
    }

    private static function report(): string
    {
        return (string) json_encode([
            'app' => 'web',
            'appVersion' => '0.1.0',
            'path' => '/events',
            'message' => 'Failed to fetch',
            'stack' => [],
            'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/144.0',
            'occurredAt' => '2026-11-04T18:00:00.000Z',
        ]);
    }
}
