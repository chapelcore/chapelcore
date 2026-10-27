<?php

/**
 * PhpVersionProbe.php — the doctor's `php.version` check.
 *
 * Why: ChapelCore needs PHP 8.4 or newer, and the host's selector can quietly be on an
 * older version (DOMAIN-MODEL §5.8; hosting runbook §3). Below 8.4 the check is down,
 * with the cPanel setting that fixes it.
 *
 * How it connects: one of the doctor's probes; it checks the PHP that runs the doctor
 * (the command line), unless a test gives it a version.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class PhpVersionProbe implements DoctorProbe
{
    private const string MINIMUM = '8.4.0';

    public function __construct(private string $version = PHP_VERSION) {}

    public function name(): string
    {
        return 'php.version';
    }

    public function check(): DoctorCheck
    {
        if (version_compare($this->version, self::MINIMUM, '>=')) {
            return new DoctorCheck($this->name(), Status::Ok, "PHP {$this->version}", null);
        }

        return new DoctorCheck(
            $this->name(),
            Status::Down,
            "PHP {$this->version} is too old; ChapelCore needs 8.4 or newer",
            'Set PHP to 8.5 in cPanel → PHP Tweaks → Account settings.',
        );
    }
}
