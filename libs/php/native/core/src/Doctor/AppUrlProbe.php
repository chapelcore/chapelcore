<?php

/**
 * AppUrlProbe.php — the doctor's `app.url` check.
 *
 * Why: on test and production the site must be served over https:// (session cookies
 * are Secure, CLAUDE.md), and links and redirects are built from APP_URL. On a
 * developer's machine http://localhost is normal, so it isn't checked in dev
 * (DOMAIN-MODEL §5.8).
 *
 * How it connects: one of the doctor's probes; Laravel (1.5) passes its environment and
 * configured app URL.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class AppUrlProbe implements DoctorProbe
{
    public function __construct(
        private Environment $environment,
        private string $url,
    ) {}

    public function name(): string
    {
        return 'app.url';
    }

    public function check(): DoctorCheck
    {
        if ($this->environment === Environment::Dev) {
            return new DoctorCheck($this->name(), Status::Ok, "Not checked in {$this->environment->value}", null);
        }
        if (str_starts_with($this->url, 'https://')) {
            return new DoctorCheck($this->name(), Status::Ok, $this->url, null);
        }

        return new DoctorCheck(
            $this->name(),
            Status::Down,
            $this->url === '' ? 'APP_URL is not set' : "APP_URL is {$this->url}, not an https:// address",
            "Set APP_URL to the site's https:// address.",
        );
    }
}
