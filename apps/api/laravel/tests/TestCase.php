<?php

/**
 * TestCase.php — the base class for the Laravel app's tests.
 *
 * Why: Laravel's TestCase boots the whole app before each test, so routes, the
 * database connection and facades work as they do for a real request. The helpers
 * below are shared by every test: one checks JSON against the contract (CLAUDE.md:
 * transport JSON follows the contract schemas), one gives the test its own empty log
 * folder, so tests read only the log lines they caused.
 *
 * How it connects: every test in tests/ extends it; phpunit.xml sets the test
 * environment and the test database.
 */

declare(strict_types=1);

namespace Tests;

use ChapelCore\Contract\ContractValidator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private ?string $logDirectory = null;

    protected function tearDown(): void
    {
        if ($this->logDirectory !== null) {
            array_map(unlink(...), glob($this->logDirectory.'/*') ?: []);
            rmdir($this->logDirectory);
            $this->logDirectory = null;
        }
        parent::tearDown();
    }

    /** Fails with the schema's complaints when $json doesn't match the contract schema. */
    protected function assertMatchesContract(string $json, string $schema): void
    {
        $this->assertSame([], (new ContractValidator)->errorsInJson($json, $schema), "Not a valid {$schema}");
    }

    /** Points the app's log at a new, empty folder for this test only. */
    protected function useEmptyLogFolder(): void
    {
        $this->logDirectory = sys_get_temp_dir().'/chapelcore-test-logs-'.bin2hex(random_bytes(6));
        mkdir($this->logDirectory);
        config(['logging.channels.chapelcore.path' => $this->logDirectory]);
    }

    /**
     * Every line the app has logged during this test, decoded.
     *
     * @return list<array<string, mixed>>
     */
    protected function logEntries(): array
    {
        $this->assertNotNull($this->logDirectory, 'Call useEmptyLogFolder() first');
        $entries = [];
        foreach (glob($this->logDirectory.'/chapelcore-*.log') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $this->assertMatchesContract($line, 'log-entry');
                /** @var array<string, mixed> $entry */
                $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
