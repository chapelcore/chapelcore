<?php

/**
 * HealthTest.php — GET /health reports the version and three checks, with the HTTP
 * status the deploy relies on.
 *
 * Why: DOMAIN-MODEL §4 (HealthStatus, HealthCheck): the checks are database, scheduler
 * and storage, in that order; 200 when ok or degraded, 503 when down, always as an
 * Envelope<HealthStatus>, never an error envelope; messages are generic because /health
 * is public. The deploy polls it for the new version (phase-1 §10), so the version
 * comes from version.json, never from code.
 *
 * How it connects: tests routes/api.php, HealthController, the adapters in app/Health,
 * the chapelcore:heartbeat command and their wiring in AppServiceProvider, against a
 * real MariaDB. The rules themselves are tested in the core. ApiPrefixTest covers the
 * /api layout.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Core\Model\RefCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    private string $heartbeatPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->heartbeatPath = $this->useTemporaryHeartbeatFile();
        $this->fixClockAt('2026-11-03T18:00:00Z');
    }

    public function test_health_is_ok_when_everything_works(): void
    {
        $this->recordHeartbeat();

        $response = $this->get('/health');

        $this->assertHealthResponse($response, 200, 'ok');
        $this->assertSame([
            ['name' => 'database', 'status' => 'ok', 'message' => null],
            ['name' => 'scheduler', 'status' => 'ok', 'message' => null],
            ['name' => 'storage', 'status' => 'ok', 'message' => null],
        ], $response->json('data.checks'));
    }

    public function test_the_reference_code_is_in_the_header_and_the_body(): void
    {
        $response = $this->get('/health');

        $header = (string) $response->headers->get('X-Request-Id');
        $this->assertMatchesRegularExpression(RefCode::PATTERN, $header);
        $this->assertSame($header, $response->json('meta.requestId'));
    }

    public function test_the_version_comes_from_the_repository_version_json(): void
    {
        $file = dirname(__DIR__, 5).'/version.json';
        $expected = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        $response = $this->get('/health');

        $this->assertSame($expected, $response->json('data.version'));
    }

    public function test_the_version_json_path_comes_from_config(): void
    {
        $file = $this->temporaryDirectory().'/version.json';
        file_put_contents($file, '{"version": "9.8.7", "codename": "Testing"}');
        config(['chapelcore.version_path' => $file]);

        $response = $this->get('/health');

        $this->assertSame(['version' => '9.8.7', 'codename' => 'Testing'], $response->json('data.version'));
    }

    public function test_a_missing_version_json_is_an_internal_error(): void
    {
        config(['chapelcore.version_path' => $this->temporaryDirectory().'/missing.json']);

        $response = $this->get('/health');

        $response->assertStatus(500);
        $this->assertMatchesContract((string) $response->getContent(), 'error-envelope');
        $this->assertSame('INTERNAL_ERROR', $response->json('error.code'));
    }

    public function test_health_is_degraded_when_no_heartbeat_was_ever_recorded(): void
    {
        $response = $this->get('/health');

        $this->assertHealthResponse($response, 200, 'degraded');
        $this->assertCheck($response, 'scheduler', 'degraded', 'Scheduled tasks have not run recently');
    }

    public function test_health_is_degraded_when_the_heartbeat_is_older_than_five_minutes(): void
    {
        $this->recordHeartbeat();
        $this->fixClockAt('2026-11-03T18:05:01Z');

        $response = $this->get('/health');

        $this->assertHealthResponse($response, 200, 'degraded');
        $this->assertCheck($response, 'scheduler', 'degraded', 'Scheduled tasks have not run recently');
    }

    public function test_an_unreadable_heartbeat_file_counts_as_no_heartbeat(): void
    {
        file_put_contents($this->heartbeatPath, 'not a time');

        $response = $this->get('/health');

        $this->assertHealthResponse($response, 200, 'degraded');
        $this->assertCheck($response, 'scheduler', 'degraded', 'Scheduled tasks have not run recently');
    }

    public function test_health_is_down_when_the_database_is_unreachable(): void
    {
        $this->recordHeartbeat();
        config(['database.connections.mariadb.port' => 1]); // nothing listens on port 1
        DB::purge('mariadb');

        $response = $this->get('/health');

        $this->assertHealthResponse($response, 503, 'down');
        $this->assertSame([
            ['name' => 'database', 'status' => 'down', 'message' => 'Database unavailable'],
            ['name' => 'scheduler', 'status' => 'ok', 'message' => null],
            ['name' => 'storage', 'status' => 'ok', 'message' => null],
        ], $response->json('data.checks'));
    }

    public function test_health_is_down_when_storage_is_not_writable(): void
    {
        $this->recordHeartbeat();
        $this->app->useStoragePath($this->temporaryDirectory().'/missing');

        $response = $this->get('/health');

        $this->assertHealthResponse($response, 503, 'down');
        $this->assertCheck($response, 'storage', 'down', 'Storage not writable');
    }

    private function recordHeartbeat(): void
    {
        $this->assertSame(0, $this->runCommand('chapelcore:heartbeat')[0]);
    }

    /** @param TestResponse<Response> $response */
    private function assertHealthResponse(TestResponse $response, int $status, string $overall): void
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertMatchesContract((string) $response->getContent(), 'health-response');
        $this->assertSame($overall, $response->json('data.status'));
    }

    /** @param TestResponse<Response> $response */
    private function assertCheck(TestResponse $response, string $name, string $status, ?string $message): void
    {
        $checks = array_column((array) $response->json('data.checks'), null, 'name');
        $this->assertSame(['name' => $name, 'status' => $status, 'message' => $message], $checks[$name] ?? null);
    }
}
