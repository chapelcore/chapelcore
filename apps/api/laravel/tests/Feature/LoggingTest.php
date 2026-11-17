<?php

/**
 * LoggingTest.php — Laravel logs through the core's JsonLogger: one redacted JSON line
 * per entry, carrying the request's reference code.
 *
 * Why: CLAUDE.md, Logging: everything goes through the logging port and is redacted
 * before it is written; a person who quotes a reference code must lead us to the
 * matching log line. Risk R12 (does Laravel accept a logger that isn't Monolog?) is
 * answered by the first test.
 *
 * How it connects: tests config/logging.php and app/Logging. Each test logs into its
 * own empty folder (TestCase::useEmptyLogFolder()), and every line is checked against
 * the contract's log-entry schema.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Core\Diagnostics\JsonLogger;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class LoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useEmptyLogFolder();
    }

    public function test_laravel_logs_through_the_core_json_logger(): void
    {
        $channel = Log::channel();

        $this->assertInstanceOf(Logger::class, $channel);
        $this->assertInstanceOf(JsonLogger::class, $channel->getLogger());
    }

    public function test_a_failure_is_logged_with_the_reference_code_the_caller_saw(): void
    {
        Route::get('/fails', fn () => throw new RuntimeException('The database went away'));

        $response = $this->get('/fails');

        $entries = $this->logEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('error', $entries[0]['level']);
        $this->assertSame('app', $entries[0]['channel']);
        $this->assertSame($response->json('meta.requestId'), $entries[0]['requestId']);
        $exception = $entries[0]['exception'];
        $this->assertIsArray($exception);
        $this->assertSame(RuntimeException::class, $exception['type']);
    }

    public function test_a_password_in_a_failure_is_redacted_in_the_log(): void
    {
        Route::get('/fails', fn () => throw new RuntimeException('Could not connect to mysql://chapel:hunter2@db/chapelcore'));

        $this->get('/fails');

        $this->assertCount(1, $this->logEntries());
        $this->assertStringNotContainsString('hunter2', (string) json_encode($this->logEntries()));
    }

    public function test_an_address_that_does_not_exist_is_not_logged(): void
    {
        $this->get('/no-such-page');

        $this->assertSame([], $this->logEntries());
    }

    public function test_an_entry_outside_a_request_has_no_reference_code(): void
    {
        Log::info('The heartbeat was recorded');

        $entries = $this->logEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(['info', 'The heartbeat was recorded', null], [$entries[0]['level'], $entries[0]['message'], $entries[0]['requestId']]);
    }
}
