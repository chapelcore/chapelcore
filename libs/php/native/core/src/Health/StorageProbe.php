<?php

/**
 * StorageProbe.php — the `storage` health check (DOMAIN-MODEL 0.4 §4).
 *
 * Why: logs, caches and uploads need a writable storage folder. The probe writes and
 * removes a small file instead of only asking whether the folder looks writable, so a
 * full disk quota (a real risk on shared hosting) is caught too.
 *
 * How it connects: Laravel (1.5) gives it the storage folder's path; HealthService
 * runs it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Health;

use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\Status;

final readonly class StorageProbe implements HealthProbe
{
    public const string NAME = 'storage';

    public function __construct(private string $directory) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function check(): HealthCheck
    {
        return $this->canWrite()
            ? new HealthCheck(self::NAME, Status::Ok, null)
            : new HealthCheck(self::NAME, Status::Down, 'Storage not writable');
    }

    private function canWrite(): bool
    {
        if (!is_dir($this->directory)) {
            return false;
        }

        $file = $this->directory . '/.chapelcore-health-' . bin2hex(random_bytes(4));
        // "@" turns a failed write into a false result instead of a PHP warning: here the
        // failure is the answer, not an error.
        if (@file_put_contents($file, 'ok') !== 2) {
            @unlink($file);

            return false;
        }

        return unlink($file);
    }
}
