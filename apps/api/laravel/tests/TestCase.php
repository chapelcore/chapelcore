<?php

/**
 * TestCase.php — the base class for the Laravel app's tests.
 *
 * Why: Laravel's TestCase boots the whole app before each test, so routes, the
 * database connection and facades work as they do for a real request. The helpers
 * below are shared by every test: one checks JSON against the contract (CLAUDE.md:
 * transport JSON follows the contract schemas); others give a test its own empty
 * folders (logs, the heartbeat), a fixed clock, or environment variables set before
 * the app boots, for the settings Laravel reads only once (the route prefix, the
 * database connection).
 *
 * How it connects: every test in tests/ extends it; phpunit.xml sets the test
 * environment and the test database.
 */

declare(strict_types=1);

namespace Tests;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Clock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    /**
     * Environment variables set before the app boots; a test class overrides this.
     *
     * @var array<string, string>
     */
    protected array $bootEnvironment = [];

    /** @var array<string, array{?string, ?string}> each replaced variable's old ENV and SERVER values */
    private array $replacedEnvironment = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    private ?string $logDirectory = null;

    protected function setUp(): void
    {
        foreach ($this->bootEnvironment as $name => $value) {
            $this->replacedEnvironment[$name] = [self::stringOrNull($_ENV[$name] ?? null), self::stringOrNull($_SERVER[$name] ?? null)];
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $file) {
                unlink($directory.'/'.$file);
            }
            rmdir($directory);
        }
        $this->temporaryDirectories = [];
        $this->logDirectory = null;
        parent::tearDown();
        foreach ($this->replacedEnvironment as $name => [$env, $server]) {
            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
        $this->replacedEnvironment = [];
    }

    /** Fails with the schema's complaints when $json doesn't match the contract schema. */
    protected function assertMatchesContract(string $json, string $schema): void
    {
        $this->assertSame([], (new ContractValidator)->errorsInJson($json, $schema), "Not a valid {$schema}");
    }

    /** A new, empty folder, removed after the test (files only, no subfolders). */
    protected function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/chapelcore-test-'.bin2hex(random_bytes(6));
        mkdir($directory);

        return $this->temporaryDirectories[] = $directory;
    }

    /** Points the app's log at a new, empty folder for this test only. */
    protected function useEmptyLogFolder(): void
    {
        $this->logDirectory = $this->temporaryDirectory();
        config(['logging.channels.chapelcore.path' => $this->logDirectory]);
    }

    /** Points the heartbeat at a file (not yet written) in a new, empty folder; returns its path. */
    protected function useTemporaryHeartbeatFile(): string
    {
        $path = $this->temporaryDirectory().'/heartbeat';
        config(['chapelcore.heartbeat_path' => $path]);

        return $path;
    }

    /** Makes the app's Clock always answer $instant (ISO 8601, e.g. 2026-11-03T18:00:00Z). */
    protected function fixClockAt(string $instant): void
    {
        $this->app->instance(Clock::class, new readonly class(new DateTimeImmutable($instant)) implements Clock
        {
            public function __construct(private DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        });
    }

    /**
     * Runs an artisan command for real and returns its exit code and output.
     *
     * Laravel's own artisan() expectations (assertSuccessful, expectsOutput) need
     * Mockery, which the trimmed skeleton leaves out.
     *
     * @return array{int, string}
     */
    protected function runCommand(string $command): array
    {
        $this->withoutMockingConsoleOutput();
        $exitCode = $this->artisan($command);
        $this->assertIsInt($exitCode);

        return [$exitCode, Artisan::output()];
    }

    /** @return list<string> every route the app serves, as "GET|HEAD uri" */
    protected function servedRoutes(): array
    {
        return array_map(
            static fn (RouteDefinition $route): string => implode('|', $route->methods()).' '.$route->uri(),
            array_values(Route::getRoutes()->getRoutes()),
        );
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

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
