<?php

/**
 * PhpArgon2Probe.php — the doctor's `php.argon2` check.
 *
 * Why: passwords are hashed with Argon2id when PHP was built with it (CLAUDE.md,
 * Passwords). Without it they fall back to bcrypt, which is still safe, so the check is
 * degraded rather than down (DOMAIN-MODEL §5.8; plan risk R5).
 *
 * How it connects: one of the doctor's probes; it asks the PHP that runs the doctor
 * whether PASSWORD_ARGON2ID is defined, unless a test says.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class PhpArgon2Probe implements DoctorProbe
{
    private bool $available;

    public function __construct(?bool $available = null)
    {
        $this->available = $available ?? defined('PASSWORD_ARGON2ID');
    }

    public function name(): string
    {
        return 'php.argon2';
    }

    public function check(): DoctorCheck
    {
        return $this->available
            ? new DoctorCheck($this->name(), Status::Ok, 'Argon2id is available for passwords', null)
            : new DoctorCheck(
                $this->name(),
                Status::Degraded,
                'Argon2id is not available, so passwords fall back to bcrypt',
                'Ask your host for PHP built with Argon2 (libargon2).',
            );
    }
}
