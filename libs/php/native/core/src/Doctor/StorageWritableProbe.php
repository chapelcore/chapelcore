<?php

/**
 * StorageWritableProbe.php — the doctor's `storage.writable` check.
 *
 * Why: the same rule as the public `storage` health check (DOMAIN-MODEL §5.8), but the
 * doctor isn't public, so it names the folder and the permission that fixes it.
 *
 * How it connects: one of the doctor's probes; it asks the health check's StorageProbe,
 * so the two can never disagree.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Health\StorageProbe;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class StorageWritableProbe implements DoctorProbe
{
    public function __construct(private string $directory) {}

    public function name(): string
    {
        return 'storage.writable';
    }

    public function check(): DoctorCheck
    {
        if ((new StorageProbe($this->directory))->check()->status === Status::Ok) {
            return new DoctorCheck($this->name(), Status::Ok, "{$this->directory} is writable", null);
        }

        return new DoctorCheck(
            $this->name(),
            Status::Down,
            "{$this->directory} is missing or not writable",
            "Make sure {$this->directory} exists and has permissions 755, so the account's user can write to it"
                . ' (cPanel → File Manager, or chmod 755 over SSH).',
        );
    }
}
