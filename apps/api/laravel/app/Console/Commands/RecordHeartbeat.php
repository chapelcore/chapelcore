<?php

/**
 * RecordHeartbeat.php — `php artisan chapelcore:heartbeat`: records that cron is running.
 *
 * Why: cron runs this every minute (phase-1 §10), and /health's scheduler check is
 * degraded once the last beat is more than 5 minutes old. It prints nothing when it
 * works, so cron sends no mail; when it can't write, it says why and exits 1, so cron's
 * mail reports it.
 *
 * How it connects: Laravel finds it in app/Console/Commands; it writes through
 * HeartbeatFile, with the time from the core's Clock (both bound in AppServiceProvider).
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Health\HeartbeatFile;
use ChapelCore\Core\Clock;
use Illuminate\Console\Command;

final class RecordHeartbeat extends Command
{
    /** @var string */
    protected $signature = 'chapelcore:heartbeat';

    /** @var string */
    protected $description = 'Records that cron is running (cron runs this every minute).';

    public function handle(HeartbeatFile $heartbeat, Clock $clock): int
    {
        if ($heartbeat->record($clock->now())) {
            return self::SUCCESS;
        }

        $this->error("Could not record the heartbeat in {$heartbeat->path}. Check that the folder exists and is writable.");

        return self::FAILURE;
    }
}
