<?php

/**
 * HeartbeatFile.php — keeps the time of the last heartbeat in one small file.
 *
 * Why: the heartbeat lives in storage/, not the database, so a database outage doesn't
 * also fail the scheduler check (1.5 E1). The file holds one contract Timestamp. A new
 * beat is written to a temporary file and then renamed into place, so /health never
 * reads a half-written time. A missing or unreadable file means "no heartbeat", never
 * an error.
 *
 * How it connects: implements the core's HeartbeatStore, which SchedulerProbe (and the
 * doctor's scheduler check, checkpoint 5) reads; the chapelcore:heartbeat command calls
 * record(). AppServiceProvider gives it the path from config/chapelcore.php.
 */

declare(strict_types=1);

namespace App\Health;

use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Transport\Timestamp;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HeartbeatFile implements HeartbeatStore
{
    public function __construct(public string $path) {}

    public function lastBeat(): ?DateTimeImmutable
    {
        $text = is_file($this->path) ? @file_get_contents($this->path) : false;
        if ($text === false) {
            return null;
        }

        try {
            return Timestamp::parse(trim($text));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Records $instant as the last heartbeat; false when the file cannot be written.
     */
    public function record(DateTimeImmutable $instant): bool
    {
        $temporary = $this->path.'.tmp-'.bin2hex(random_bytes(4));
        // "@" turns a failed write into a false result instead of a PHP warning
        if (@file_put_contents($temporary, Timestamp::format($instant)) === false) {
            return false;
        }
        if (! @rename($temporary, $this->path)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }
}
