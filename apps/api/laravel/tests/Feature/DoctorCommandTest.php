<?php

/**
 * DoctorCommandTest.php — `php artisan chapelcore:doctor` prints the doctor's report as
 * plain text and exits 1 when any check is down.
 *
 * Why: the doctor is how someone on SSH finds out what is wrong with a server, and the
 * deploy (1.9) runs it before migrating: exit code 1 stops the deploy (DoctorReport::
 * exitCode). Degraded still exits 0, because the site runs. Plain text, one line per
 * check, so it reads well in a terminal and in a deploy log (E5); a check that isn't ok
 * shows its fix on the next line.
 *
 * How it connects: tests app/Console/Commands/RunDoctor with made-up checks, so the
 * output doesn't depend on this machine. DoctorChecksTest runs the real nine checks.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Core\Doctor\Doctor;
use ChapelCore\Core\Doctor\DoctorProbe;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use Tests\TestCase;

final class DoctorCommandTest extends TestCase
{
    public function test_the_report_lists_every_check_and_the_fix_for_each_problem(): void
    {
        config(['chapelcore.env' => 'test']);
        $this->useChecks(
            new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null),
            new DoctorCheck('scheduler.heartbeat', Status::Degraded, 'No heartbeat has been recorded yet', 'Add the cron line from the hosting runbook §8.'),
            new DoctorCheck('app.url', Status::Down, 'APP_URL is http://test.example.org, not an https:// address', "Set APP_URL to the site's https:// address."),
        );

        [, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertSame(<<<'TEXT'
            ChapelCore doctor (test)

            ok        php.version          PHP 8.5.11
            degraded  scheduler.heartbeat  No heartbeat has been recorded yet
                                           Fix: Add the cron line from the hosting runbook §8.
            down      app.url              APP_URL is http://test.example.org, not an https:// address
                                           Fix: Set APP_URL to the site's https:// address.

            Result: down (1 down, 1 degraded)

            TEXT, $output);
    }

    public function test_all_ok_exits_0(): void
    {
        $this->useChecks(
            new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null),
            new DoctorCheck('app.url', Status::Ok, 'Not checked in dev', null),
        );

        [$exitCode, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertSame(0, $exitCode);
        $this->assertStringEndsWith("\nResult: ok (all 2 checks passed)\n", $output);
    }

    public function test_degraded_exits_0_because_the_site_still_runs(): void
    {
        $this->useChecks(
            new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null),
            new DoctorCheck('php.argon2', Status::Degraded, 'Argon2id is not available', 'Ask your host for PHP built with Argon2 (libargon2).'),
        );

        [$exitCode, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertSame(0, $exitCode);
        $this->assertStringEndsWith("\nResult: degraded (1 degraded)\n", $output);
    }

    public function test_any_down_exits_1(): void
    {
        $this->useChecks(
            new DoctorCheck('php.version', Status::Down, 'PHP 8.3.0 is too old; ChapelCore needs 8.4 or newer', 'Set PHP to 8.5 in cPanel → PHP Tweaks → Account settings.'),
            new DoctorCheck('app.debug', Status::Down, 'Debug is on in production, so error pages could show code and settings', "Set APP_DEBUG=false in the server's .env."),
        );

        [$exitCode, $output] = $this->runCommand('chapelcore:doctor');

        $this->assertSame(1, $exitCode);
        $this->assertStringEndsWith("\nResult: down (2 down)\n", $output);
    }

    /** Makes the command run a doctor that reports exactly these checks. */
    private function useChecks(DoctorCheck ...$checks): void
    {
        $probes = array_map(static fn (DoctorCheck $check): DoctorProbe => new readonly class($check) implements DoctorProbe
        {
            public function __construct(private DoctorCheck $result) {}

            public function name(): string
            {
                return $this->result->name;
            }

            public function check(): DoctorCheck
            {
                return $this->result;
            }
        }, $checks);

        $this->app->instance(Doctor::class, new Doctor(...$probes));
    }
}
