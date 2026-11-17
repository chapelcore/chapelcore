<?php

/**
 * DoctorChecksTest.php — the doctor runs the nine Phase 1 checks against this app's real
 * settings, database, storage folder and heartbeat.
 *
 * Why: the checks themselves are tested in the core; this proves the Laravel side hands
 * them the right things: CHAPELCORE_ENV, APP_URL, APP_DEBUG, the database connection,
 * storage/, the heartbeat file and CHAPELCORE_TLS_HOSTS (DOMAIN-MODEL §5.8, phase-1
 * §6.3). It also checks what the deploy relies on: a bad setting outside dev is down,
 * with its fix, and the command exits 1.
 *
 * How it connects: tests the Doctor binding in AppServiceProvider, the Environment it
 * reads from config/chapelcore.php, and RunDoctor. The database.charset check reads the
 * real MariaDB through App\Doctor\MariaDbCharsets, so the tables are migrated first.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class DoctorChecksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTemporaryHeartbeatFile();
    }

    public function test_the_nine_checks_run_in_the_domain_model_order(): void
    {
        $this->assertSame([
            'php.version',
            'php.extensions',
            'php.argon2',
            'database.charset',
            'storage.writable',
            'scheduler.heartbeat',
            'app.url',
            'app.debug',
            'tls.certificate',
        ], array_keys($this->doctorLines()));
    }

    public function test_the_report_names_the_environment(): void
    {
        config(['chapelcore.env' => 'production']);

        [, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertStringStartsWith("ChapelCore doctor (production)\n", $output);
    }

    public function test_in_dev_the_url_debug_and_certificate_checks_are_not_run(): void
    {
        config(['chapelcore.env' => 'dev', 'app.url' => 'http://localhost', 'app.debug' => true]);

        $lines = $this->doctorLines();

        foreach (['app.url', 'app.debug', 'tls.certificate'] as $name) {
            $this->assertSame(['ok', 'Not checked in dev'], $lines[$name], $name);
        }
    }

    public function test_an_http_app_url_outside_dev_is_down_with_its_fix(): void
    {
        config(['chapelcore.env' => 'test', 'app.url' => 'http://test.example.org']);

        [$exitCode, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertSame(1, $exitCode);
        $this->assertSame(['down', 'APP_URL is http://test.example.org, not an https:// address'], $this->doctorLines($output)['app.url']);
        $this->assertStringContainsString("Fix: Set APP_URL to the site's https:// address.", $output);
    }

    public function test_an_https_app_url_outside_dev_is_ok(): void
    {
        config(['chapelcore.env' => 'test', 'app.url' => 'https://api.test.example.org']);

        $this->assertSame(['ok', 'https://api.test.example.org'], $this->doctorLines()['app.url']);
    }

    public function test_debug_on_in_production_is_down(): void
    {
        config(['chapelcore.env' => 'production', 'app.debug' => true]);

        [$exitCode, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertSame(1, $exitCode);
        $this->assertSame('down', $this->doctorLines($output)['app.debug'][0]);
        $this->assertStringContainsString("Fix: Set APP_DEBUG=false in the server's .env.", $output);
    }

    public function test_debug_off_in_production_is_ok(): void
    {
        config(['chapelcore.env' => 'production', 'app.debug' => false]);

        $this->assertSame(['ok', 'Debug is off'], $this->doctorLines()['app.debug']);
    }

    public function test_the_database_and_its_tables_are_checked_for_utf8mb4(): void
    {
        // the migrated tables: cache, cache_locks and Laravel's migrations
        $this->assertSame(['ok', 'utf8mb4: the database and 3 tables'], $this->doctorLines()['database.charset']);
    }

    public function test_the_storage_folder_is_checked(): void
    {
        $this->assertSame(['ok', storage_path().' is writable'], $this->doctorLines()['storage.writable']);
    }

    public function test_the_heartbeat_file_is_checked(): void
    {
        $this->assertSame(['degraded', 'No heartbeat has been recorded yet'], $this->doctorLines()['scheduler.heartbeat']);

        $this->fixClockAt('2026-11-05T18:00:00Z');
        $this->runCommand('chapelcore:heartbeat');

        $this->assertSame(['ok', 'Last heartbeat at 2026-11-05T18:00:00.000Z'], $this->doctorLines()['scheduler.heartbeat']);
    }

    public function test_with_no_certificate_hosts_outside_dev_the_certificate_check_is_down(): void
    {
        config(['chapelcore.env' => 'test', 'chapelcore.tls_hosts' => []]);

        $this->assertSame(['down', 'The check could not run: No hosts were given to check'], $this->doctorLines()['tls.certificate']);
    }

    public function test_an_unknown_environment_name_stops_the_doctor(): void
    {
        config(['chapelcore.env' => 'staging']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CHAPELCORE_ENV must be dev, test or production, not "staging"');

        $this->runCommand('chapelcore:doctor');
    }

    /**
     * The report's check lines, by check name: [status, message].
     *
     * @return array<string, array{string, string}>
     */
    private function doctorLines(?string $output = null): array
    {
        $output ??= $this->runCommand('chapelcore:doctor')[1];
        $lines = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(ok|degraded|down) +(\S+) +(.+)$/', $line, $match) === 1) {
                $lines[$match[2]] = [$match[1], $match[3]];
            }
        }

        return $lines;
    }
}
