<?php

/**
 * AppDebugProbe.php — the doctor's `app.debug` check.
 *
 * Why: debug mode is for developers; left on in production it can expose code and
 * settings, so it is down there (DOMAIN-MODEL §5.8). It is only checked in production,
 * because on dev and test it may be on deliberately.
 *
 * How it connects: one of the doctor's probes; Laravel (1.5) passes its environment and
 * debug setting.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class AppDebugProbe implements DoctorProbe
{
    public function __construct(
        private Environment $environment,
        private bool $debug,
    ) {}

    public function name(): string
    {
        return 'app.debug';
    }

    public function check(): DoctorCheck
    {
        if ($this->environment !== Environment::Production) {
            return new DoctorCheck($this->name(), Status::Ok, "Not checked in {$this->environment->value}", null);
        }

        return $this->debug
            ? new DoctorCheck(
                $this->name(),
                Status::Down,
                'Debug is on in production, so error pages could show code and settings',
                "Set APP_DEBUG=false in the server's .env.",
            )
            : new DoctorCheck($this->name(), Status::Ok, 'Debug is off', null);
    }
}
