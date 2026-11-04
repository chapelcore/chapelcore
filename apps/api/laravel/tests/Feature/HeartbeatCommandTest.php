<?php

/**
 * HeartbeatCommandTest.php — `php artisan chapelcore:heartbeat` records when cron last
 * ran.
 *
 * Why: cron runs it every minute (phase-1 §10), and the scheduler health check reads
 * the time it writes (E1: a file in storage/, so a database outage doesn't also fail
 * the scheduler check). It prints nothing when it works, so cron sends no mail, and
 * exits 1 with a message when it can't write.
 *
 * How it connects: tests app/Console/Commands/RecordHeartbeat and app/Health/HeartbeatFile
 * through the HeartbeatStore port that SchedulerProbe reads.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Core\Health\HeartbeatStore;
use DateTimeImmutable;
use Tests\TestCase;

final class HeartbeatCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useTemporaryHeartbeatFile();
    }

    public function test_the_command_records_the_current_time_silently(): void
    {
        $this->fixClockAt('2026-11-03T18:00:00Z');

        $this->assertSame([0, ''], $this->runCommand('chapelcore:heartbeat'));

        $this->assertEquals(new DateTimeImmutable('2026-11-03T18:00:00Z'), $this->lastBeat());
    }

    public function test_each_beat_replaces_the_last(): void
    {
        $this->fixClockAt('2026-11-03T18:00:00Z');
        $this->assertSame(0, $this->runCommand('chapelcore:heartbeat')[0]);
        $this->fixClockAt('2026-11-03T18:01:00Z');
        $this->assertSame(0, $this->runCommand('chapelcore:heartbeat')[0]);

        $this->assertEquals(new DateTimeImmutable('2026-11-03T18:01:00Z'), $this->lastBeat());
    }

    public function test_there_is_no_beat_before_the_command_first_runs(): void
    {
        $this->assertNull($this->lastBeat());
    }

    public function test_the_command_fails_when_it_cannot_write(): void
    {
        $path = $this->temporaryDirectory().'/missing/heartbeat';
        config(['chapelcore.heartbeat_path' => $path]);

        [$exitCode, $output] = $this->runCommand('chapelcore:heartbeat');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Could not record the heartbeat', $output);
        $this->assertFileDoesNotExist($path);
    }

    private function lastBeat(): ?DateTimeImmutable
    {
        return $this->app->make(HeartbeatStore::class)->lastBeat();
    }
}
