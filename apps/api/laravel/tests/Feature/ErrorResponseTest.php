<?php

/**
 * ErrorResponseTest.php — every error the API sends is an ErrorEnvelope with a
 * reference code, never an HTML page or a stack trace.
 *
 * Why: DOMAIN-MODEL §4 (ErrorEnvelope, error codes, ResponseMeta) and CLAUDE.md
 * (users see a reference code, never a stack trace). The reference code ties together
 * what a person sees, the X-Request-Id header and the log; the API always makes its
 * own, so a caller can't plant one in our logs (1.5 E3). meta.servedBy names the
 * Laravel and PHP versions actually running. Errors stay JSON even with debug on or
 * when a browser asks for HTML (R13).
 *
 * A client error (4xx) is never answered as INTERNAL_ERROR: a status without its own code
 * gets 400 BAD_REQUEST (DOMAIN-MODEL §4), so a mistake in a request never looks like a
 * fault on our side.
 *
 * How it connects: tests bootstrap/app.php (the request-ID middleware and the error
 * rendering), app/Http and app/Exceptions. The client-error endpoint's own 413, 422 and
 * 429 are tested in ClientErrorIntakeTest and ClientErrorRateLimitTest.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Core\Model\RefCode;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ErrorResponseTest extends TestCase
{
    public function test_an_unknown_address_gets_not_found(): void
    {
        $response = $this->get('/no-such-page');

        $this->assertErrorEnvelope($response, 404, 'NOT_FOUND');
    }

    public function test_a_wrong_method_gets_method_not_allowed(): void
    {
        Route::get('/only-get', fn (): string => 'ok');

        $response = $this->post('/only-get');

        $this->assertErrorEnvelope($response, 405, 'METHOD_NOT_ALLOWED');
        $response->assertHeader('Allow', 'GET, HEAD');
    }

    public function test_a_failure_gets_internal_error_without_any_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/fails', fn () => throw new RuntimeException('Login hunter2 rejected by the database'));

        $response = $this->get('/fails', ['Accept' => 'text/html']);

        $this->assertErrorEnvelope($response, 500, 'INTERNAL_ERROR');
        $body = (string) $response->getContent();
        foreach (['hunter2', 'RuntimeException', '.php', 'trace'] as $detail) {
            $this->assertStringNotContainsString($detail, $body);
        }
    }

    public function test_maintenance_mode_gets_service_unavailable(): void
    {
        config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
        $this->app->maintenanceMode()->activate([]);

        $response = $this->get('/no-such-page');

        $this->assertErrorEnvelope($response, 503, 'SERVICE_UNAVAILABLE');
    }

    public function test_an_error_is_json_even_when_a_browser_asks_for_html(): void
    {
        $response = $this->get('/no-such-page', ['Accept' => 'text/html']);

        $this->assertErrorEnvelope($response, 404, 'NOT_FOUND');
    }

    public function test_the_reference_code_is_in_the_header_and_the_body(): void
    {
        $response = $this->get('/no-such-page');

        $header = (string) $response->headers->get('X-Request-Id');
        $this->assertMatchesRegularExpression(RefCode::PATTERN, $header);
        $this->assertSame($header, $response->json('meta.requestId'));
    }

    public function test_each_request_gets_a_new_reference_code(): void
    {
        $first = $this->get('/no-such-page')->headers->get('X-Request-Id');
        $second = $this->get('/no-such-page')->headers->get('X-Request-Id');

        $this->assertNotSame($first, $second);
    }

    public function test_a_reference_code_sent_by_the_caller_is_ignored(): void
    {
        $response = $this->get('/no-such-page', ['X-Request-Id' => '7F3K-92QD']);

        $this->assertNotSame('7F3K-92QD', $response->headers->get('X-Request-Id'));
        $this->assertNotSame('7F3K-92QD', $response->json('meta.requestId'));
    }

    public function test_a_body_over_the_server_upload_limit_gets_payload_too_large(): void
    {
        if ((int) ini_get('post_max_size') <= 0) {
            $this->markTestSkipped('post_max_size is unlimited on this PHP, so no body is too large');
        }

        $response = $this->call('POST', '/health', server: ['CONTENT_LENGTH' => (string) PHP_INT_MAX, 'CONTENT_TYPE' => 'application/json']);

        $this->assertErrorEnvelope($response, 413, 'PAYLOAD_TOO_LARGE');
    }

    public function test_a_malformed_host_gets_bad_request(): void
    {
        // the test client always sends the app's own host, so the request goes straight to the kernel
        $request = Request::create('/health', 'GET', server: ['HTTP_HOST' => 'bad host!']);

        $kernel = $this->app->make(Kernel::class);
        $base = $kernel->handle($request);
        $kernel->terminate($request, $base);
        $response = TestResponse::fromBaseResponse($base);

        $this->assertErrorEnvelope($response, 400, 'BAD_REQUEST');
    }

    /** @return iterable<string, array{int}> */
    public static function otherClientErrors(): iterable
    {
        // provisional: Phase 3 gives 401, 403 and 419 their AUTH_* codes and own statuses
        yield '401' => [401];
        yield '403' => [403];
        yield '419' => [419];
        yield '418' => [418];
    }

    #[DataProvider('otherClientErrors')]
    public function test_any_other_client_error_gets_bad_request(int $status): void
    {
        Route::get('/refused', fn () => abort($status));

        $this->assertErrorEnvelope($this->get('/refused'), 400, 'BAD_REQUEST');
    }

    public function test_a_validation_error_status_gets_validation_failed(): void
    {
        Route::get('/invalid', fn () => abort(422));

        $this->assertErrorEnvelope($this->get('/invalid'), 422, 'VALIDATION_FAILED');
    }

    public function test_any_other_server_error_stays_internal_error(): void
    {
        Route::get('/gateway', fn () => abort(502));

        $this->assertErrorEnvelope($this->get('/gateway'), 500, 'INTERNAL_ERROR');
    }

    public function test_the_meta_names_what_served_the_response(): void
    {
        $response = $this->get('/no-such-page');

        $this->assertSame(
            ['backend' => 'laravel', 'backendVersion' => Application::VERSION, 'language' => 'php', 'languageVersion' => PHP_VERSION],
            $response->json('meta.servedBy'),
        );
    }

    /** @param TestResponse<covariant Response> $response */
    private function assertErrorEnvelope(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertMatchesContract((string) $response->getContent(), 'error-envelope');
        $this->assertSame($code, $response->json('error.code'));
    }
}
