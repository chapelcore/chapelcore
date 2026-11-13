<?php

/**
 * ClientErrorIntakeTest.php — POST /diagnostics/client-errors accepts a browser error
 * report exactly as sent, logs it redacted, and answers 202 with no body.
 *
 * Why: DOMAIN-MODEL §5.8 (ClientErrorReport): the endpoint is public, so the body is
 * untrusted. Over 8 KB is PAYLOAD_TOO_LARGE; a broken field is VALIDATION_FAILED with
 * a message for every field, the same messages the core's parser gives; the server
 * never trims. An accepted report becomes one warning on the diagnostics.client
 * channel, redacted like every log entry (CLAUDE.md, Logging). A rejected one is not
 * logged, so junk can't fill the log.
 *
 * How it connects: tests routes/api.php, ClientErrorController and the client_errors
 * log channel in config/logging.php, with the contract's fixtures as the examples. The
 * rules themselves are tested in the core. The route is rate-limited on the database
 * cache table, so every test starts from freshly migrated tables (RefreshDatabase);
 * ClientErrorRateLimitTest covers the limit.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Transport\ClientErrorReportParser;
use ChapelCore\Core\Transport\RejectedInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ClientErrorIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useEmptyLogFolder();
    }

    /** @return iterable<string, array{string}> every valid fixture that fits in 8 KB */
    public static function validReports(): iterable
    {
        foreach (self::fixtures('valid') as $name => $file) {
            if (filesize($file) <= ClientErrorReportParser::MAX_BYTES) {
                yield $name => [$file];
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReports(): iterable
    {
        foreach (self::fixtures('invalid') as $name => $file) {
            yield $name => [$file];
        }
    }

    #[DataProvider('validReports')]
    public function test_a_valid_report_is_accepted_with_no_body(string $file): void
    {
        $response = $this->sendReport(self::fixtureBody($file));

        $response->assertStatus(202);
        $this->assertSame('', $response->getContent());
        $this->assertNotNull($response->headers->get('X-Request-Id'));
    }

    public function test_an_accepted_report_is_logged_once_as_a_warning(): void
    {
        $response = $this->sendReport(self::report());

        $entries = $this->logEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('warning', $entries[0]['level']);
        $this->assertSame('diagnostics.client', $entries[0]['channel']);
        $this->assertSame("Cannot read properties of undefined (reading 'checks')", $entries[0]['message']);
        $this->assertSame($response->headers->get('X-Request-Id'), $entries[0]['requestId']);
        $this->assertNull($entries[0]['exception']);
    }

    public function test_the_log_entry_keeps_the_rest_of_the_report_as_its_context(): void
    {
        $this->sendReport(self::report());

        $entries = $this->logEntries();
        $this->assertCount(1, $entries);
        $this->assertSame([
            'app' => 'admin',
            'appVersion' => '0.1.0',
            'path' => '/admin/status',
            'stack' => ['TypeError: Cannot read properties of undefined', '    at StatusPage (status.vue:42:13)'],
            'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
            'occurredAt' => '2026-10-21T15:04:05.123Z',
        ], $entries[0]['context']);
    }

    public function test_personal_data_in_a_report_is_redacted_in_the_log(): void
    {
        $this->sendReport(self::report([
            'message' => 'Could not save pat@example.org',
            'stack' => ['Error: 401 for Bearer eyJhbGciOiJIUzI1NiJ9.e30.c2ln', '    at save (members.vue:12:3)'],
        ]));

        $logged = (string) json_encode($this->logEntries());
        $this->assertStringNotContainsString('pat@example.org', $logged);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9', $logged);
    }

    public function test_a_report_sent_as_plain_text_is_accepted(): void
    {
        // navigator.sendBeacon() sends a string body as text/plain
        $response = $this->sendReport(self::report(), 'text/plain;charset=UTF-8');

        $response->assertStatus(202);
        $this->assertCount(1, $this->logEntries());
    }

    public function test_a_report_over_8_kb_is_payload_too_large(): void
    {
        // valid field by field, but about 28 KB in all; the server never trims
        $response = $this->sendReport(self::fixtureBody(self::fixtures('valid')['limits-reached']));

        $this->assertErrorEnvelope($response, 413, 'PAYLOAD_TOO_LARGE');
        $this->assertSame([], $this->logEntries());
    }

    #[DataProvider('invalidReports')]
    public function test_an_invalid_report_gets_the_core_field_messages(string $file): void
    {
        $body = self::fixtureBody($file);

        $response = $this->sendReport($body);

        $this->assertErrorEnvelope($response, 422, 'VALIDATION_FAILED');
        $this->assertSame(self::coreFieldMessages($body), $response->json('error.fields'));
        $this->assertSame([], $this->logEntries());
    }

    public function test_an_empty_message_is_reported_as_empty_not_as_missing(): void
    {
        // Laravel turns "" into null in $request->input(); the report must be read raw
        $response = $this->sendReport(self::report(['message' => '']));

        $this->assertSame(['message' => ['Must not be empty.']], $response->json('error.fields'));
    }

    public function test_a_body_that_is_not_a_json_object_is_rejected(): void
    {
        $response = $this->sendReport('not json');

        $this->assertErrorEnvelope($response, 422, 'VALIDATION_FAILED');
        $this->assertSame('The error report must be a JSON object.', $response->json('error.message'));
    }

    /** @return TestResponse<Response> */
    private function sendReport(string $body, string $contentType = 'application/json'): TestResponse
    {
        return $this->call('POST', '/diagnostics/client-errors', server: ['CONTENT_TYPE' => $contentType], content: $body);
    }

    /** @param TestResponse<Response> $response */
    private function assertErrorEnvelope(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status);
        $this->assertMatchesContract((string) $response->getContent(), 'error-envelope');
        $this->assertSame($code, $response->json('error.code'));
    }

    /**
     * The admin.json fixture as a request body, with some fields replaced.
     *
     * @param  array<string, mixed>  $changes
     */
    private static function report(array $changes = []): string
    {
        $report = json_decode(self::fixtureBody(self::fixtures('valid')['admin']), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);

        return json_encode([...$report, ...$changes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<array-key, mixed> what the core's parser says about $body, field by field */
    private static function coreFieldMessages(string $body): array
    {
        try {
            (new ClientErrorReportParser)->parse($body);
        } catch (RejectedInput $rejected) {
            return $rejected->error->fields;
        }
        self::fail('The core accepted an invalid fixture');
    }

    /** @return array<string, string> fixture name => file */
    private static function fixtures(string $kind): array
    {
        $files = glob(dirname(ContractValidator::schemasDir())."/fixtures/{$kind}/client-error-report/*.json") ?: [];
        $named = [];
        foreach ($files as $file) {
            $named[basename($file, '.json')] = $file;
        }

        return $named;
    }

    /** The report in a fixture file, as a request body; invalid fixtures keep it under "data". */
    private static function fixtureBody(string $file): string
    {
        $fixture = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        $report = str_contains($file, '/invalid/') ? $fixture['data'] : $fixture;

        return json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
